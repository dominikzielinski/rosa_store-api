<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

use App\Exceptions\ClientErrorException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Modules\Orders\DataTransferObjects\OrderStoreDto;
use Modules\Orders\Jobs\PushOrderToBackofficeJob;
use Modules\Orders\Models\Order;
use Modules\Orders\Repositories\Interfaces\OrderRepositoryInterface;
use Modules\Pim\Models\Box;
use Throwable;

readonly class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $repository,
        protected DatabaseManager $databaseManager,
    ) {}

    /**
     * Create an order with snapshot data + server-side total. Never trust FE prices —
     * we re-fetch each Box from PIM and compute the total from current DB values.
     *
     * Retries once on a unique-constraint violation (the order_number generator does
     * its own pre-check, but a race between two concurrent inserts could still hit
     * the DB-level unique index).
     *
     * @throws Throwable
     */
    public function store(OrderStoreDto $dto): Order
    {
        try {
            $order = $this->createOrderTransaction($dto);
        } catch (QueryException $e) {
            // SQLSTATE 23000 — unique violation on order_number under concurrent inserts.
            if ($e->getCode() === '23000') {
                $order = $this->createOrderTransaction($dto);
            } else {
                throw $e;
            }
        }

        // Always push to backoffice on create so the order shows up in the panel
        // immediately, regardless of payment method. For p24 the initial push goes
        // out with paymentStatus=pending; once the verified webhook arrives, we
        // re-dispatch the job which sends paymentStatus=paid (backoffice idempotency
        // upgrades pending→paid without duplicate invoice/email).
        // Dispatched outside the transaction so a rollback can never queue an orphan.
        PushOrderToBackofficeJob::dispatch($order->id);

        return $order;
    }

    /**
     * @throws Throwable
     */
    private function createOrderTransaction(OrderStoreDto $dto): Order
    {
        return $this->databaseManager->transaction(function () use ($dto): Order {
            // 1. Resolve all boxes by ID — only buyable ones (active + available)
            $boxIds = array_map(static fn ($i) => $i->boxId, $dto->items);
            $boxes = Box::with('package')
                ->whereIn('id', $boxIds)
                ->where('active', true)
                ->where('available', true)
                ->get()
                ->keyBy('id');

            // 2. Validate every requested box exists and is buyable
            foreach ($dto->items as $item) {
                if (! $boxes->has($item->boxId)) {
                    throw new ClientErrorException(
                        "Box #{$item->boxId} jest niedostępny.",
                        422,
                    );
                }
            }

            // 3. Compute server-side total + snapshot rows for items table
            $totalAmountPln = 0;
            $itemsForInsert = [];

            foreach ($dto->items as $item) {
                $box = $boxes->get($item->boxId);
                $unitPrice = $box->getEffectivePricePln();
                $itemTotal = $unitPrice * $item->quantity;
                $totalAmountPln += $itemTotal;

                $itemsForInsert[] = [
                    'box_id' => $box->id,
                    'box_slug' => $box->slug,
                    'box_name' => $box->name,
                    'package_slug' => $box->package->slug,
                    'gender' => $box->gender->value,
                    'quantity' => $item->quantity,
                    'unit_price_pln' => $unitPrice,
                    'total_price_pln' => $itemTotal,
                ];
            }

            // 4. Create the order. Repository uses forceFill so `status` (non-fillable
            // by design — protects against request-level mass-assignment) can be set here.
            $order = $this->repository->create([
                'order_number' => $this->repository->generateOrderNumber(),
                'status' => $dto->paymentMethod->initialOrderStatus(),
                'payment_method' => $dto->paymentMethod,
                'total_amount_pln' => $totalAmountPln,

                'billing_type' => $dto->billingType,
                'billing_first_name' => $dto->firstName,
                'billing_last_name' => $dto->lastName,
                'billing_company' => $dto->companyName,
                'billing_nip' => $dto->nip,
                'billing_email' => $dto->email,
                'billing_phone' => $dto->phone,
                'billing_street' => $dto->street,
                'billing_house_number' => $dto->houseNumber,
                'billing_postal_code' => $dto->postalCode,
                'billing_city' => $dto->city,

                'note' => $dto->note,
                'consent_terms' => $dto->consentTerms,
                'consent_marketing' => $dto->consentMarketing,

                'ip_address' => $dto->ipAddress,
                'user_agent' => $dto->userAgent,
            ]);

            $order->items()->createMany($itemsForInsert);

            return $order->load('items');
        });
    }
}
