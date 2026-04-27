<?php

declare(strict_types=1);

use App\Exceptions\ServerErrorException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Modules\Orders\Enums\OrderStatusEnum;
use Modules\Orders\Jobs\PushOrderToBackofficeJob;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\P24Client;
use Modules\Orders\Services\P24VerifyParams;
use Modules\Pim\Models\Box;
use Modules\Pim\Models\Package;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('p24.sandbox', true);
    config()->set('p24.merchant_id', 372297);
    config()->set('p24.pos_id', 372297);
    config()->set('p24.reports_key', 'test-reports-key');
    config()->set('p24.crc_key', 'test-crc-key');
    config()->set('p24.currency', 'PLN');
    config()->set('p24.country', 'PL');
    config()->set('p24.language', 'pl');
    config()->set('p24.timeout', 5);

    Bus::fake();
});

function p24Package(): Package
{
    return Package::create(['slug' => 'std', 'name' => 'Standard', 'price_pln' => 35000, 'active' => true]);
}

function p24Box(Package $pkg): Box
{
    return Box::create([
        'package_id' => $pkg->id,
        'slug' => 'std-w-1',
        'name' => 'Box Std W #1',
        'gender' => 'women',
        'price_pln' => 35000,
        'active' => true,
        'available' => true,
    ]);
}

function p24OrderPayload(int $boxId): array
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
            'houseNumber' => '1/2',
            'postalCode' => '00-001',
            'city' => 'Warszawa',
        ],
        'consentTerms' => true,
    ];
}

// ─── Register flow ────────────────────────────────────────────────────────

it('registers a p24 transaction and returns the hosted payment URL', function () {
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'abc123']], 200),
    ]);

    $box = p24Box(p24Package());

    $response = postJson('/api/orders', p24OrderPayload($box->id))->assertCreated();

    expect($response->json('data.redirectUrl'))
        ->toBe('https://sandbox.przelewy24.pl/trnRequest/abc123');

    $order = Order::first();
    expect($order->p24_token)->toBe('abc123');
    expect($order->p24_session_id)->toBe($order->order_number);
    expect($order->status)->toBe(OrderStatusEnum::PendingPayment);
});

it('signs the register payload with SHA-384 of session+merchant+amount+currency+crc', function () {
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200),
    ]);

    $box = p24Box(p24Package());
    postJson('/api/orders', p24OrderPayload($box->id))->assertCreated();

    Http::assertSent(function ($request) {
        $body = $request->data();
        $expectedSign = hash('sha384', json_encode([
            'sessionId' => $body['sessionId'],
            'merchantId' => 372297,
            'amount' => $body['amount'],
            'currency' => 'PLN',
            'crc' => 'test-crc-key',
        ], JSON_UNESCAPED_SLASHES));

        return $body['sign'] === $expectedSign;
    });
});

it('does not push to backoffice on register — waits for verified webhook', function () {
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200),
    ]);

    postJson('/api/orders', p24OrderPayload(p24Box(p24Package())->id))->assertCreated();

    Bus::assertNotDispatched(PushOrderToBackofficeJob::class);
});

it('returns 502 when P24 register fails', function () {
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['error' => 'oops'], 500),
    ]);

    postJson('/api/orders', p24OrderPayload(p24Box(p24Package())->id))->assertStatus(502);

    // Order is left in pending_payment without a token — auto-cancelled by cron after 24h.
    $order = Order::first();
    expect($order)->not->toBeNull();
    expect($order->p24_token)->toBeNull();
    expect($order->status)->toBe(OrderStatusEnum::PendingPayment);
});

// ─── Webhook flow ─────────────────────────────────────────────────────────

function p24SignNotification(array $payload, string $crc): string
{
    return hash('sha384', json_encode([
        'merchantId' => $payload['merchantId'],
        'posId' => $payload['posId'],
        'sessionId' => $payload['sessionId'],
        'amount' => $payload['amount'],
        'originAmount' => $payload['originAmount'],
        'currency' => $payload['currency'],
        'orderId' => $payload['orderId'],
        'methodId' => $payload['methodId'],
        'statement' => $payload['statement'],
        'crc' => $crc,
    ], JSON_UNESCAPED_SLASHES));
}

it('rejects webhook with invalid signature', function () {
    $payload = [
        'merchantId' => 372297, 'posId' => 372297,
        'sessionId' => 'RD-12345678', 'amount' => 35000, 'originAmount' => 35000,
        'currency' => 'PLN', 'orderId' => 99999, 'methodId' => 1, 'statement' => 'foo',
        'sign' => 'totally-wrong',
    ];

    postJson('/api/p24/webhook', $payload)->assertStatus(401);
});

it('rejects webhook for unknown sessionId with 404', function () {
    $payload = [
        'merchantId' => 372297, 'posId' => 372297,
        'sessionId' => 'RD-99999999', 'amount' => 35000, 'originAmount' => 35000,
        'currency' => 'PLN', 'orderId' => 99999, 'methodId' => 1, 'statement' => 'foo',
    ];
    $payload['sign'] = p24SignNotification($payload, 'test-crc-key');

    postJson('/api/p24/webhook', $payload)->assertStatus(404);
});

it('rejects webhook with mismatched amount', function () {
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200),
    ]);
    postJson('/api/orders', p24OrderPayload(p24Box(p24Package())->id))->assertCreated();
    $order = Order::first();

    $payload = [
        'merchantId' => 372297, 'posId' => 372297,
        'sessionId' => $order->order_number, 'amount' => 1, 'originAmount' => 1,
        'currency' => 'PLN', 'orderId' => 12345, 'methodId' => 1, 'statement' => 'foo',
    ];
    $payload['sign'] = p24SignNotification($payload, 'test-crc-key');

    postJson('/api/p24/webhook', $payload)->assertStatus(422);

    expect($order->fresh()->p24_paid_at)->toBeNull();
});

it('marks order paid + dispatches push on valid webhook', function () {
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200),
        '*/api/v1/transaction/verify' => Http::response(['data' => ['status' => 'success']], 200),
    ]);

    postJson('/api/orders', p24OrderPayload(p24Box(p24Package())->id))->assertCreated();
    $order = Order::first();

    $payload = [
        'merchantId' => 372297, 'posId' => 372297,
        'sessionId' => $order->order_number, 'amount' => 35000, 'originAmount' => 35000,
        'currency' => 'PLN', 'orderId' => 12345, 'methodId' => 1, 'statement' => 'TR/12345',
    ];
    $payload['sign'] = p24SignNotification($payload, 'test-crc-key');

    postJson('/api/p24/webhook', $payload)->assertOk();

    $order->refresh();
    expect($order->status)->toBe(OrderStatusEnum::Paid);
    expect($order->p24_paid_at)->not->toBeNull();
    expect($order->p24_order_id)->toBe(12345);
    Bus::assertDispatched(PushOrderToBackofficeJob::class);
});

it('is idempotent — second webhook for same order is no-op', function () {
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200),
        '*/api/v1/transaction/verify' => Http::response(['data' => ['status' => 'success']], 200),
    ]);

    postJson('/api/orders', p24OrderPayload(p24Box(p24Package())->id))->assertCreated();
    $order = Order::first();

    $payload = [
        'merchantId' => 372297, 'posId' => 372297,
        'sessionId' => $order->order_number, 'amount' => 35000, 'originAmount' => 35000,
        'currency' => 'PLN', 'orderId' => 12345, 'methodId' => 1, 'statement' => 'TR/12345',
    ];
    $payload['sign'] = p24SignNotification($payload, 'test-crc-key');

    postJson('/api/p24/webhook', $payload)->assertOk();
    $firstPaidAt = $order->fresh()->p24_paid_at;

    postJson('/api/p24/webhook', $payload)->assertOk();

    expect($order->fresh()->p24_paid_at?->timestamp)->toBe($firstPaidAt?->timestamp);
    // Push job dispatched only once
    Bus::assertDispatchedTimes(PushOrderToBackofficeJob::class, 1);
});

it('returns 502 when P24 verify rejects the payment', function () {
    Http::fake([
        '*/api/v1/transaction/register' => Http::response(['data' => ['token' => 'tok']], 200),
        '*/api/v1/transaction/verify' => Http::response(['data' => ['status' => 'failure']], 200),
    ]);

    postJson('/api/orders', p24OrderPayload(p24Box(p24Package())->id))->assertCreated();
    $order = Order::first();

    $payload = [
        'merchantId' => 372297, 'posId' => 372297,
        'sessionId' => $order->order_number, 'amount' => 35000, 'originAmount' => 35000,
        'currency' => 'PLN', 'orderId' => 12345, 'methodId' => 1, 'statement' => 'TR/12345',
    ];
    $payload['sign'] = p24SignNotification($payload, 'test-crc-key');

    postJson('/api/p24/webhook', $payload)->assertStatus(502);

    expect($order->fresh()->p24_paid_at)->toBeNull();
});

// ─── P24Client unit ───────────────────────────────────────────────────────

it('rejects when P24 not configured', function () {
    config()->set('p24.merchant_id', 0);

    expect(fn () => app(P24Client::class)->verify(
        new P24VerifyParams('RD-1', 1, 1),
    ))->toThrow(ServerErrorException::class);
});
