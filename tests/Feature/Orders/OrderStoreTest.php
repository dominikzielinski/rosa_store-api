<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Modules\Orders\Models\Order;
use Modules\Pim\Models\Box;
use Modules\Pim\Models\Package;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    // OrderStoreTest only tests the HTTP endpoint + service. Push to backoffice
    // is covered separately in BackofficePushTest. Fake the bus so jobs don't
    // try to hit the real network in this suite.
    Bus::fake();

    // Stub P24 register so p24-method orders don't try to hit the real sandbox.
    config()->set('p24.merchant_id', 372297);
    config()->set('p24.pos_id', 372297);
    config()->set('p24.reports_key', 'test-reports-key');
    config()->set('p24.crc_key', 'test-crc-key');
    Http::fake([
        '*/api/v1/transaction/register' => Http::response([
            'data' => ['token' => 'test-token-abc'],
        ], 200),
    ]);
});

function ordersTestPackage(int $pricePln = 80000, string $slug = 'premium'): Package
{
    return Package::create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'price_pln' => $pricePln,
        'active' => true,
    ]);
}

function ordersTestBox(Package $pkg, ?int $pricePln = null, ?string $slug = null): Box
{
    return Box::create([
        'package_id' => $pkg->id,
        'slug' => $slug ?? "{$pkg->slug}-women-".uniqid(),
        'gender' => 'women',
        'name' => 'Test Box',
        'price_pln' => $pricePln,
        'available' => true,
        'active' => true,
    ]);
}

function validBillingPayload(): array
{
    return [
        'type' => 'individual',
        'firstName' => 'Jan',
        'lastName' => 'Kowalski',
        'email' => 'jan@example.com',
        'phone' => '+48500600700',
        'street' => 'Testowa',
        'houseNumber' => '1/2',
        'postalCode' => '00-001',
        'city' => 'Warszawa',
    ];
}

function validOrderPayload(int $boxId, int $quantity = 1): array
{
    return [
        'items' => [['boxId' => $boxId, 'quantity' => $quantity]],
        'paymentMethod' => 'transfer',
        'billing' => validBillingPayload(),
        'note' => 'Prezent dla mamy',
        'consentTerms' => true,
        'consentMarketing' => false,
    ];
}

// ─── Happy path ───────────────────────────────────────────────────────────

it('POST /api/orders creates an order with transfer method', function () {
    $pkg = ordersTestPackage(80000);
    $box = ordersTestBox($pkg);

    $response = postJson('/api/orders', validOrderPayload($box->id, 2));

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => ['orderNumber', 'paymentMethod', 'totalAmountPln', 'redirectUrl'],
            'message',
        ])
        ->assertJsonPath('data.paymentMethod.id', 'transfer')
        ->assertJsonPath('data.totalAmountPln', 160000);

    expect(Order::count())->toBe(1);

    $order = Order::first();
    expect($order->status->value)->toBe('accepted');
    expect($order->total_amount_pln)->toBe(160000);
    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->quantity)->toBe(2);
});

it('returns redirectUrl pointing to /dziekujemy with order params', function () {
    $box = ordersTestBox(ordersTestPackage(80000));

    $response = postJson('/api/orders', validOrderPayload($box->id));

    $url = $response->json('data.redirectUrl');
    expect($url)->toContain('/dziekujemy?');
    expect($url)->toContain('order=RD-');
    expect($url)->toContain('method=transfer');
});

it('creates order with p24 method and pending_payment status', function () {
    $box = ordersTestBox(ordersTestPackage(80000));

    $payload = validOrderPayload($box->id);
    $payload['paymentMethod'] = 'p24';

    postJson('/api/orders', $payload)->assertCreated();

    expect(Order::first()->status->value)->toBe('pending_payment');
});

// ─── Server-side total — anti-tamper ──────────────────────────────────────

it('server calculates total from PIM, ignoring any client-supplied price', function () {
    $box = ordersTestBox(ordersTestPackage(80000));  // package price 800 zł

    // Klient wysyła tylko boxId + quantity. Nie ma jak podać niższej ceny.
    $response = postJson('/api/orders', validOrderPayload($box->id, 3));

    expect($response->json('data.totalAmountPln'))->toBe(240000);
    expect(Order::first()->total_amount_pln)->toBe(240000);
});

it('uses box price override when set, not package price', function () {
    $pkg = ordersTestPackage(80000);
    // Box has its own price_pln=50000 (cheaper than package)
    $box = ordersTestBox($pkg, pricePln: 50000);

    postJson('/api/orders', validOrderPayload($box->id, 2))->assertCreated();

    expect(Order::first()->total_amount_pln)->toBe(100000);
});

// ─── Snapshot ─────────────────────────────────────────────────────────────

it('snapshots box data into order_items at creation time', function () {
    $pkg = ordersTestPackage(80000, 'premium');
    $box = ordersTestBox($pkg, slug: 'premium-women-1');
    $box->update(['name' => 'Box "Spa w domu"']);

    postJson('/api/orders', validOrderPayload($box->id))->assertCreated();

    $item = Order::first()->items->first();
    expect($item->box_slug)->toBe('premium-women-1');
    expect($item->box_name)->toBe('Box "Spa w domu"');
    expect($item->package_slug)->toBe('premium');
    expect($item->unit_price_pln)->toBe(80000);
});

it('item name in snapshot does not change when box renamed in PIM', function () {
    $box = ordersTestBox(ordersTestPackage(80000));
    postJson('/api/orders', validOrderPayload($box->id))->assertCreated();

    $box->update(['name' => 'New name from backoffice']);

    expect(Order::first()->items->first()->box_name)->not->toBe('New name from backoffice');
});

// ─── Validation ───────────────────────────────────────────────────────────

it('rejects empty cart', function () {
    $payload = validOrderPayload(1);
    $payload['items'] = [];
    postJson('/api/orders', $payload)->assertJsonValidationErrors(['items']);
});

it('rejects non-existent box', function () {
    $payload = validOrderPayload(99999);
    postJson('/api/orders', $payload)->assertJsonValidationErrors(['items.0.boxId']);
});

it('rejects unavailable box', function () {
    $pkg = ordersTestPackage();
    $box = ordersTestBox($pkg);
    $box->update(['available' => false]);

    postJson('/api/orders', validOrderPayload($box->id))->assertStatus(422);
});

it('rejects inactive box', function () {
    $pkg = ordersTestPackage();
    $box = ordersTestBox($pkg);
    $box->update(['active' => false]);

    postJson('/api/orders', validOrderPayload($box->id))->assertStatus(422);
});

it('rejects missing consent', function () {
    $box = ordersTestBox(ordersTestPackage());
    $payload = validOrderPayload($box->id);
    $payload['consentTerms'] = false;

    postJson('/api/orders', $payload)->assertJsonValidationErrors(['consentTerms']);
});

it('rejects invalid email', function () {
    $box = ordersTestBox(ordersTestPackage());
    $payload = validOrderPayload($box->id);
    $payload['billing']['email'] = 'not-an-email';

    postJson('/api/orders', $payload)->assertJsonValidationErrors(['billing.email']);
});

it('rejects invalid postal code', function () {
    $box = ordersTestBox(ordersTestPackage());
    $payload = validOrderPayload($box->id);
    $payload['billing']['postalCode'] = '12345';

    postJson('/api/orders', $payload)->assertJsonValidationErrors(['billing.postalCode']);
});

it('rejects company billing without companyName/nip', function () {
    $box = ordersTestBox(ordersTestPackage());
    $payload = validOrderPayload($box->id);
    $payload['billing']['type'] = 'company';
    $payload['billing']['companyName'] = '';
    $payload['billing']['nip'] = '';

    postJson('/api/orders', $payload)
        ->assertJsonValidationErrors(['billing.companyName', 'billing.nip']);
});

it('accepts company billing with valid NIP', function () {
    $box = ordersTestBox(ordersTestPackage());
    $payload = validOrderPayload($box->id);
    $payload['billing']['type'] = 'company';
    $payload['billing']['companyName'] = 'Acme Sp. z o.o.';
    $payload['billing']['nip'] = '1234567890';

    postJson('/api/orders', $payload)->assertCreated();

    expect(Order::first()->billing_nip)->toBe('1234567890');
});

it('strips dashes from NIP before storage', function () {
    $box = ordersTestBox(ordersTestPackage());
    $payload = validOrderPayload($box->id);
    $payload['billing']['type'] = 'company';
    $payload['billing']['companyName'] = 'Test';
    $payload['billing']['nip'] = '123-456-78-90';

    postJson('/api/orders', $payload)->assertCreated();

    expect(Order::first()->billing_nip)->toBe('1234567890');
});

it('lowercases email before storage', function () {
    $box = ordersTestBox(ordersTestPackage());
    $payload = validOrderPayload($box->id);
    $payload['billing']['email'] = 'UPPER@EXAMPLE.COM';

    postJson('/api/orders', $payload)->assertCreated();

    expect(Order::first()->billing_email)->toBe('upper@example.com');
});

it('silently accepts honeypot-filled submissions without persisting an order', function () {
    $box = ordersTestBox(ordersTestPackage());
    $payload = validOrderPayload($box->id);
    $payload['website'] = 'http://spam.example.com';

    postJson('/api/orders', $payload)
        ->assertCreated()
        ->assertJsonPath('data.orderNumber', 'RD-00000000');

    // No real order should have been created — bot gets a fake-success response
    expect(Order::count())->toBe(0);
});

it('records IP and user-agent', function () {
    $box = ordersTestBox(ordersTestPackage());

    postJson('/api/orders', validOrderPayload($box->id), ['User-Agent' => 'Test/1.0'])
        ->assertCreated();

    $order = Order::first();
    expect($order->user_agent)->toBe('Test/1.0');
    expect($order->ip_address)->not->toBeNull();
});

// ─── Status endpoint ──────────────────────────────────────────────────────

it('GET /api/orders/{orderNumber}/status returns minimal status payload', function () {
    $box = ordersTestBox(ordersTestPackage());
    $createResponse = postJson('/api/orders', validOrderPayload($box->id));
    $orderNumber = $createResponse->json('data.orderNumber');

    \Pest\Laravel\getJson("/api/orders/{$orderNumber}/status")
        ->assertOk()
        ->assertJsonPath('data.orderNumber', $orderNumber)
        ->assertJsonPath('data.status.id', 'accepted')
        ->assertJsonPath('data.paymentMethod.id', 'transfer')
        ->assertJsonPath('data.isPaid', false);
});

it('status endpoint returns 404 for unknown order', function () {
    \Pest\Laravel\getJson('/api/orders/RD-99999999/status')->assertStatus(404);
});

// ─── Order number generation ──────────────────────────────────────────────

it('generates unique RD-XXXXXXXX order numbers', function () {
    $box = ordersTestBox(ordersTestPackage());

    postJson('/api/orders', validOrderPayload($box->id))->assertCreated();
    postJson('/api/orders', validOrderPayload($box->id))->assertCreated();

    $numbers = Order::pluck('order_number');
    expect($numbers->unique())->toHaveCount(2);
    expect($numbers->first())->toStartWith('RD-');
});
