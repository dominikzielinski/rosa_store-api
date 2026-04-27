<?php

declare(strict_types=1);

namespace Modules\Orders\DataTransferObjects;

use Modules\Orders\Enums\BillingTypeEnum;
use Modules\Orders\Enums\PaymentMethodEnum;
use Modules\Orders\Requests\OrderStoreRequest;

final readonly class OrderStoreDto
{
    /**
     * @param  array<int, OrderItemDto>  $items
     */
    public function __construct(
        public array $items,
        public PaymentMethodEnum $paymentMethod,

        public BillingTypeEnum $billingType,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $companyName,
        public ?string $nip,
        public string $email,
        public string $phone,
        public string $street,
        public string $houseNumber,
        public string $postalCode,
        public string $city,

        public ?string $note,
        public bool $consentTerms,
        public bool $consentMarketing,

        public ?string $ipAddress,
        public ?string $userAgent,
    ) {}

    public static function fromRequest(OrderStoreRequest $request): self
    {
        $v = $request->validated();
        $billing = $v['billing'];

        return new self(
            items: array_map(
                static fn (array $item): OrderItemDto => OrderItemDto::fromArray($item),
                $v['items'],
            ),
            paymentMethod: PaymentMethodEnum::from($v['paymentMethod']),

            billingType: BillingTypeEnum::from($billing['type']),
            firstName: self::nullIfEmpty($billing['firstName'] ?? null),
            lastName: self::nullIfEmpty($billing['lastName'] ?? null),
            companyName: self::nullIfEmpty($billing['companyName'] ?? null),
            nip: self::normalizeNip($billing['nip'] ?? null),
            email: mb_strtolower(trim((string) $billing['email'])),
            phone: self::normalizePhone((string) $billing['phone']),
            street: trim((string) $billing['street']),
            houseNumber: trim((string) $billing['houseNumber']),
            postalCode: trim((string) $billing['postalCode']),
            city: trim((string) $billing['city']),

            note: self::nullIfEmpty($v['note'] ?? null),
            consentTerms: (bool) $v['consentTerms'],
            consentMarketing: (bool) ($v['consentMarketing'] ?? false),

            ipAddress: $request->ip(),
            userAgent: substr((string) $request->userAgent(), 0, 500) ?: null,
        );
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function normalizePhone(string $value): string
    {
        return (string) preg_replace('/\s+/', '', trim($value));
    }

    private static function normalizeNip(?string $value): ?string
    {
        $value = self::nullIfEmpty($value);

        return $value !== null ? (string) preg_replace('/[\s\-]/', '', $value) : null;
    }
}
