<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MetaConversionsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Contact\Models\ContactSubmission;
use Modules\Orders\Models\Order;

/**
 * Sends a server-side conversion event to Meta Conversions API.
 *
 * PII safety:
 *   - Job payload contains only IDs + eventName — no raw PII.
 *   - Hashing happens inside MetaConversionsClient, not here.
 *   - Failed job serialization does not capture intermediate variables.
 *
 * Retry policy:
 *   - 3 attempts, backoff 10s → 60s → 300s.
 *   - CAPI failures do NOT affect order status — this job is fire-and-forget.
 *
 * Supported event names: 'Purchase', 'Lead'.
 */
class SendMetaConversionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $eventName,
        public readonly ?int $orderId = null,
        public readonly ?int $contactSubmissionId = null,
        public readonly ?string $eventId = null,
    ) {}

    public function handle(MetaConversionsClient $client): void
    {
        match ($this->eventName) {
            'Purchase' => $this->handlePurchase($client),
            'Lead' => $this->handleLead($client),
            default => Log::warning('SendMetaConversionJob: unknown eventName', [
                'eventName' => $this->eventName,
            ]),
        };
    }

    private function handlePurchase(MetaConversionsClient $client): void
    {
        if ($this->orderId === null) {
            Log::warning('SendMetaConversionJob: orderId required for Purchase event');

            return;
        }

        $order = Order::with('items')->find($this->orderId);

        if (! $order) {
            Log::warning('SendMetaConversionJob: order not found', ['orderId' => $this->orderId]);

            return;
        }

        $client->sendPurchase($order);
    }

    private function handleLead(MetaConversionsClient $client): void
    {
        if ($this->contactSubmissionId === null) {
            Log::warning('SendMetaConversionJob: contactSubmissionId required for Lead event');

            return;
        }

        $submission = ContactSubmission::find($this->contactSubmissionId);

        if (! $submission) {
            Log::warning('SendMetaConversionJob: contact submission not found', [
                'contactSubmissionId' => $this->contactSubmissionId,
            ]);

            return;
        }

        $eventId = $this->eventId ?? "lead-{$this->contactSubmissionId}";

        $client->sendLead($submission, $eventId);
    }
}
