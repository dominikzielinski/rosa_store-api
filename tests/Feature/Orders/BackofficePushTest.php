<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Modules\Orders\Enums\OrderStatusEnum;
use Modules\Orders\Jobs\PushOrderToBackofficeJob;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\BackofficeClient;
use Modules\Pim\Models\Box;
use Modules\Pim\Models\Package;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('backoffice.url', 'https://backoffice.test');
    config()->set('backoffice.token', 'test-out-token');
    config()->set('backoffice.paths.orders', '/api/shop/orders');
    config()->set('backoffice.timeout', 5);

    // P24 stub for any test that posts a p24-method order through the controller.
    config()->set('p24.merchant_id', 372297);
    config()->set('p24.pos_id', 372297);
    config()->set('p24.reports_key', 'test-reports-key');
    config()->set('p24.crc_key', 'test-crc-key');
});

function pushPackage(): Package
{
    return Package::create(['slug' => 'std', 'name' => 'Standard', 'price_pln' => 35000, 'active' => true]);
}

function pushBox(Package $pkg): Box
{
    return Box::create([
        'package_id' => $pkg->id,
        'slug' => 'std-women-1-'.uniqid(),
        'gender' => 'women',
        'name' => 'Test Box',
        'available' => true,
        'active' => true,
    ]);
}

function pushOrderPayload(int $boxId): array
{
    return [
        'items' => [['boxId' => $boxId, 'quantity' => 1]],
        'paymentMethod' => 'transfer',
        'billing' => [
            'type' => 'individual',
            'firstName' => 'Jan',
            'lastName' => 'Kowalski',
            'email' => 'jan@example.com',
            'phone' => '+48500600700',
            'street' => 'Testowa',
            'houseNumber' => '1',
            'postalCode' => '00-001',
            'city' => 'Warszawa',
        ],
        'consentTerms' => true,
    ];
}

// ─── Dispatch ─────────────────────────────────────────────────────────────

it('dispatches push job after creating a transfer order', function () {
    Bus::fake();

    $box = pushBox(pushPackage());
    postJson('/api/orders', pushOrderPayload($box->id))->assertCreated();

    Bus::assertDispatched(PushOrderToBackofficeJob::class);
});

it('dispatches push job for p24 order on create (paymentStatus=pending)', function () {
    Bus::fake();
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200),
    ]);

    $box = pushBox(pushPackage());
    $payload = pushOrderPayload($box->id);
    $payload['paymentMethod'] = 'p24';

    postJson('/api/orders', $payload)->assertCreated();

    // Push happens immediately so the order shows up in the panel — backoffice
    // upgrades pending→paid when the verified P24 webhook arrives later.
    Bus::assertDispatched(PushOrderToBackofficeJob::class);
});

// ─── Job execution: success ──────────────────────────────────────────────

it('marks order as synced on successful push', function () {
    Http::fake([
        'backoffice.test/*' => Http::response([
            'data' => ['backofficeOrderId' => 'BO-2026-001'],
            'message' => 'Order received.',
        ], 201),
    ]);

    $box = pushBox(pushPackage());
    postJson('/api/orders', pushOrderPayload($box->id))->assertCreated();

    $order = Order::first();
    expect($order->status)->toBe(OrderStatusEnum::Synced);
    expect($order->backoffice_synced_at)->not->toBeNull();
    expect($order->backoffice_order_id)->toBe('BO-2026-001');
    expect($order->backoffice_sync_attempts)->toBe(1);
});

it('sends correct payload to backoffice', function () {
    Http::fake([
        'backoffice.test/*' => Http::response(['data' => []], 200),
    ]);

    $box = pushBox(pushPackage());
    postJson('/api/orders', pushOrderPayload($box->id))->assertCreated();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://backoffice.test/api/shop/orders'
            && $request->hasHeader('Authorization', 'Bearer test-out-token')
            && $body['paymentMethod'] === 'transfer'
            && $body['paymentStatus'] === 'pending'
            && array_key_exists('p24Notification', $body['paymentMeta'])
            && $body['billing']['email'] === 'jan@example.com'
            && count($body['items']) === 1;
    });
});

// ─── Job execution: errors ───────────────────────────────────────────────

it('marks order sync_failed on 4xx (no retry)', function () {
    Http::fake([
        'backoffice.test/*' => Http::response([
            'message' => 'Validation failed.',
            'errors' => ['items' => ['Bad data']],
        ], 422),
    ]);

    $box = pushBox(pushPackage());
    postJson('/api/orders', pushOrderPayload($box->id))->assertCreated();

    $order = Order::first();
    expect($order->status)->toBe(OrderStatusEnum::SyncFailed);
    expect($order->backoffice_last_error)->toContain('HTTP 422');
});

it('throws on 5xx so the queue retries', function () {
    Http::fake([
        'backoffice.test/*' => Http::response('boom', 503),
    ]);

    Bus::fake();
    $box = pushBox(pushPackage());
    postJson('/api/orders', pushOrderPayload($box->id))->assertCreated();

    // Manually run the job — the queue would retry on this exception
    $order = Order::first();
    $job = new PushOrderToBackofficeJob($order->id);
    $client = app(BackofficeClient::class);

    expect(fn () => $job->handle($client))
        ->toThrow(RequestException::class);

    $order->refresh();
    expect($order->backoffice_last_error)->toContain('HTTP 503');
    // Status NOT yet sync_failed — would only be set after `failed()` callback at attempt limit.
});

it('skips push for already-synced order (idempotency on duplicate dispatch)', function () {
    Http::fake([
        'backoffice.test/*' => Http::response(['data' => []], 200),
    ]);

    $box = pushBox(pushPackage());
    postJson('/api/orders', pushOrderPayload($box->id))->assertCreated();

    $order = Order::first();
    expect($order->backoffice_synced_at)->not->toBeNull();

    // Manually re-dispatch
    Http::clearResolvedInstances();
    Http::fake([
        'backoffice.test/*' => Http::response(['data' => []], 200),
    ]);

    PushOrderToBackofficeJob::dispatch($order->id);

    // No duplicate HTTP call
    Http::assertNothingSent();
});

it('returns 503 when backoffice outbound not configured', function () {
    config()->set('backoffice.url', '');
    config()->set('backoffice.token', '');

    $box = pushBox(pushPackage());
    postJson('/api/orders', pushOrderPayload($box->id))->assertCreated();

    $order = Order::first();
    expect($order->status)->toBe(OrderStatusEnum::SyncFailed);
    expect($order->backoffice_last_error)->toContain('not configured');
});
