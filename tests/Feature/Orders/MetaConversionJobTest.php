<?php

declare(strict_types=1);

use App\Jobs\SendMetaConversionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Modules\Orders\Jobs\PushOrderToBackofficeJob;
use Modules\Orders\Models\Order;
use Modules\Pim\Models\Box;
use Modules\Pim\Models\Package;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function metaJobPackage(): Package
{
    return Package::create(['slug' => 'prem', 'name' => 'Premium', 'price_pln' => 35000, 'active' => true]);
}

function metaJobBox(Package $pkg): Box
{
    return Box::create([
        'package_id' => $pkg->id,
        'slug' => 'prem-w-1',
        'name' => 'Premium Box W',
        'gender' => 'women',
        'price_pln' => 35000,
        'active' => true,
        'available' => true,
    ]);
}

function transferPayload(int $boxId, bool $consentMarketing = true, ?string $metaEventId = null): array
{
    $payload = [
        'items' => [['boxId' => $boxId, 'quantity' => 1]],
        'paymentMethod' => 'transfer',
        'billing' => [
            'type' => 'individual',
            'firstName' => 'Jan',
            'lastName' => 'Kowalski',
            'email' => 'jan@example.com',
            'phone' => '500600700',
            'street' => 'Testowa',
            'houseNumber' => '1',
            'postalCode' => '00-001',
            'city' => 'Warszawa',
        ],
        'consentTerms' => true,
        'consentMarketing' => $consentMarketing,
    ];

    if ($metaEventId !== null) {
        $payload['metaEventId'] = $metaEventId;
    }

    return $payload;
}

function p24Payload(int $boxId): array
{
    return [
        'items' => [['boxId' => $boxId, 'quantity' => 1]],
        'paymentMethod' => 'p24',
        'billing' => [
            'type' => 'individual',
            'firstName' => 'Jan',
            'lastName' => 'Kowalski',
            'email' => 'jan@example.com',
            'phone' => '500600700',
            'street' => 'Testowa',
            'houseNumber' => '1',
            'postalCode' => '00-001',
            'city' => 'Warszawa',
        ],
        'consentTerms' => true,
        'consentMarketing' => true,
    ];
}

// ─── Transfer order ───────────────────────────────────────────────────────────

it('transfer order dispatch dispatches SendMetaConversionJob alongside PushOrderToBackofficeJob', function () {
    Bus::fake();

    $box = metaJobBox(metaJobPackage());
    $eventId = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    postJson('/api/orders', transferPayload($box->id, consentMarketing: true, metaEventId: $eventId))
        ->assertCreated();

    Bus::assertDispatched(PushOrderToBackofficeJob::class);
    Bus::assertDispatched(SendMetaConversionJob::class, function (SendMetaConversionJob $job) use ($eventId) {
        expect($job->eventName)->toBe('Purchase');
        expect($job->orderId)->toBe(Order::first()->id);
        expect($job->eventId)->toBe($eventId);
        expect($job->contactSubmissionId)->toBeNull();

        return true;
    });
});

it('transfer order stores meta_event_id on the order', function () {
    Bus::fake();

    $box = metaJobBox(metaJobPackage());
    $eventId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    postJson('/api/orders', transferPayload($box->id, metaEventId: $eventId))->assertCreated();

    expect(Order::first()->meta_event_id)->toBe($eventId);
});

it('transfer order without metaEventId stores null and still dispatches job', function () {
    Bus::fake();

    $box = metaJobBox(metaJobPackage());

    postJson('/api/orders', transferPayload($box->id, metaEventId: null))->assertCreated();

    expect(Order::first()->meta_event_id)->toBeNull();

    Bus::assertDispatched(SendMetaConversionJob::class, function (SendMetaConversionJob $job) {
        expect($job->eventId)->toBeNull();

        return true;
    });
});

it('P24 order creation does NOT dispatch SendMetaConversionJob (fires only after webhook)', function () {
    Bus::fake();
    Http::fake(['*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200)]);
    config()->set('p24.merchant_id', 372297);
    config()->set('p24.pos_id', 372297);
    config()->set('p24.reports_key', 'test-key');
    config()->set('p24.crc_key', 'test-crc');

    $box = metaJobBox(metaJobPackage());
    postJson('/api/orders', p24Payload($box->id))->assertCreated();

    Bus::assertNotDispatched(SendMetaConversionJob::class);
});

// ─── P24 webhook ─────────────────────────────────────────────────────────────

it('P24 webhook dispatches SendMetaConversionJob after payment confirmed', function () {
    // Use the same Http::fake pattern as P24FlowTest — real signature, faked HTTP verify call.
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200),
        '*/api/v1/transaction/verify' => Http::response(['data' => ['status' => 'success']], 200),
    ]);
    config()->set('p24.sandbox', true);
    config()->set('p24.merchant_id', 372297);
    config()->set('p24.pos_id', 372297);
    config()->set('p24.reports_key', 'test-reports-key');
    config()->set('p24.crc_key', 'test-crc-key');

    Bus::fake();

    // 1. Create a p24 order (so we get a real order with p24_session_id + meta_event_id).
    $eventId = 'fedcba98-7654-3210-fedc-ba9876543210';
    $box = metaJobBox(metaJobPackage());
    $orderPayload = p24Payload($box->id);
    $orderPayload['metaEventId'] = $eventId;

    postJson('/api/orders', $orderPayload)->assertCreated();
    $order = Order::first();
    expect($order->meta_event_id)->toBe($eventId);

    // 2. Build a correctly signed webhook notification (same as P24FlowTest).
    $webhookPayload = [
        'merchantId' => 372297,
        'posId' => 372297,
        'sessionId' => $order->order_number,
        'amount' => 35000,
        'originAmount' => 35000,
        'currency' => 'PLN',
        'orderId' => 999,
        'methodId' => 1,
        'statement' => 'TR/999',
    ];
    $webhookPayload['sign'] = hash('sha384', json_encode([
        'merchantId' => $webhookPayload['merchantId'],
        'posId' => $webhookPayload['posId'],
        'sessionId' => $webhookPayload['sessionId'],
        'amount' => $webhookPayload['amount'],
        'originAmount' => $webhookPayload['originAmount'],
        'currency' => $webhookPayload['currency'],
        'orderId' => $webhookPayload['orderId'],
        'methodId' => $webhookPayload['methodId'],
        'statement' => $webhookPayload['statement'],
        'crc' => 'test-crc-key',
    ], JSON_UNESCAPED_SLASHES));

    postJson('/api/p24/webhook', $webhookPayload)->assertOk();

    Bus::assertDispatched(PushOrderToBackofficeJob::class);
    Bus::assertDispatched(SendMetaConversionJob::class, function (SendMetaConversionJob $job) use ($order, $eventId) {
        expect($job->eventName)->toBe('Purchase');
        expect($job->orderId)->toBe($order->id);
        expect($job->eventId)->toBe($eventId);

        return true;
    });
});

// ─── Contact / Lead ───────────────────────────────────────────────────────────

it('contact submission with consent_marketing=true dispatches Lead job', function () {
    Bus::fake();

    postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Anna Nowak',
        'email' => 'anna@example.com',
        'message' => 'Chciałabym zamówić pudełko.',
        'consentData' => true,
        'consentMarketing' => true,
    ])->assertCreated();

    Bus::assertDispatched(SendMetaConversionJob::class, function (SendMetaConversionJob $job) {
        expect($job->eventName)->toBe('Lead');
        expect($job->contactSubmissionId)->not->toBeNull();
        expect($job->orderId)->toBeNull();
        expect($job->eventId)->toStartWith('lead-');

        return true;
    });
});

it('contact submission with consent_marketing=false does NOT dispatch Lead job', function () {
    Bus::fake();

    postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Anna Nowak',
        'email' => 'anna@example.com',
        'message' => 'Chciałabym zamówić pudełko.',
        'consentData' => true,
        'consentMarketing' => false,
    ])->assertCreated();

    Bus::assertNotDispatched(SendMetaConversionJob::class);
});
