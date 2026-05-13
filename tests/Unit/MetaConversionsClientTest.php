<?php

declare(strict_types=1);

use App\Services\MetaConversionsClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Modules\Contact\Models\ContactSubmission;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Tests\TestCase;

// MetaConversionsClient is a pure service — no DB writes needed.
// We boot the Laravel app (TestCase) for config(), Http::fake(), and IoC, but skip DB refresh.
uses(TestCase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function metaConfig(): void
{
    config()->set('services.meta.pixel_id', '123456789012345');
    config()->set('services.meta.capi_access_token', 'EAAtest...');
    config()->set('services.meta.test_event_code', null);
    config()->set('services.meta.graph_api_version', 'v22.0');
}

function makeOrder(bool $consentMarketing = true, ?string $metaEventId = null): Order
{
    $order = new Order;
    $order->id = 1;
    $order->billing_email = 'jan@example.com';
    $order->billing_phone = '+48500600700';
    $order->billing_first_name = 'Jan';
    $order->billing_last_name = 'Kowalski';
    $order->billing_street = 'Testowa';
    $order->billing_house_number = '1';
    $order->billing_postal_code = '00-001';
    $order->billing_city = 'Warszawa';
    $order->total_amount_pln = 8000; // 80 PLN
    $order->consent_marketing = $consentMarketing;
    $order->meta_event_id = $metaEventId;
    $order->ip_address = '1.2.3.4';
    $order->user_agent = 'Mozilla/5.0';

    $item = new OrderItem;
    $item->box_id = 42;
    $item->quantity = 2;

    $order->setRelation('items', new Collection([$item]));

    return $order;
}

function makeSubmission(bool $consentMarketing = true): ContactSubmission
{
    $sub = new ContactSubmission;
    $sub->id = 7;
    $sub->email = 'anna@example.com';
    $sub->consent_marketing = $consentMarketing;
    $sub->ip_address = '5.6.7.8';
    $sub->user_agent = 'Safari/1.0';

    return $sub;
}

// ─── sendPurchase ─────────────────────────────────────────────────────────────

it('sends a Purchase event with correct shape to the Meta Graph API', function () {
    metaConfig();
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    $order = makeOrder(consentMarketing: true, metaEventId: 'aaaa-bbbb-cccc-dddd-eeee');

    app(MetaConversionsClient::class)->sendPurchase($order);

    Http::assertSent(function ($request) {
        $body = $request->data();

        expect($request->url())->toContain('/v22.0/123456789012345/events');
        expect($body)->toHaveKey('data');
        expect($body)->toHaveKey('access_token', 'EAAtest...');

        $event = $body['data'][0];
        expect($event['event_name'])->toBe('Purchase');
        expect($event['event_id'])->toBe('aaaa-bbbb-cccc-dddd-eeee');
        expect($event['action_source'])->toBe('website');
        expect($event['custom_data']['value'])->toBe(80.0);
        expect($event['custom_data']['currency'])->toBe('PLN');
        expect($event['custom_data']['content_type'])->toBe('product');
        expect($event['custom_data']['contents'][0]['id'])->toBe('42');
        expect($event['custom_data']['contents'][0]['quantity'])->toBe(2);

        // PII must be hashed — raw values must not appear
        $userData = $event['user_data'];
        expect($userData)->toHaveKey('em');
        expect($userData['em'])->toBe(hash('sha256', 'jan@example.com'));
        expect($userData)->not->toHaveKey('jan@example.com');

        expect($userData)->toHaveKey('ph');
        expect($userData['ph'])->toBe(hash('sha256', '48500600700'));

        expect($userData)->toHaveKey('country');
        expect($userData['country'])->toBe(hash('sha256', 'pl'));

        expect($userData['client_ip_address'])->toBe('1.2.3.4');
        expect($userData['client_user_agent'])->toBe('Mozilla/5.0');

        return true;
    });
});

it('skips Purchase when consent_marketing is false', function () {
    metaConfig();
    Http::fake();

    app(MetaConversionsClient::class)->sendPurchase(makeOrder(consentMarketing: false));

    Http::assertNothingSent();
});

it('skips Purchase when META_PIXEL_ID is missing', function () {
    config()->set('services.meta.pixel_id', null);
    config()->set('services.meta.capi_access_token', 'EAAtest...');
    Http::fake();

    app(MetaConversionsClient::class)->sendPurchase(makeOrder());

    Http::assertNothingSent();
});

it('skips Purchase when META_CAPI_ACCESS_TOKEN is missing', function () {
    config()->set('services.meta.pixel_id', '123456789012345');
    config()->set('services.meta.capi_access_token', null);
    Http::fake();

    app(MetaConversionsClient::class)->sendPurchase(makeOrder());

    Http::assertNothingSent();
});

it('omits event_id from payload when meta_event_id is null', function () {
    metaConfig();
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    app(MetaConversionsClient::class)->sendPurchase(makeOrder(metaEventId: null));

    Http::assertSent(function ($request) {
        $event = $request->data()['data'][0];
        expect($event)->not->toHaveKey('event_id');

        return true;
    });
});

it('includes test_event_code when configured', function () {
    metaConfig();
    config()->set('services.meta.test_event_code', 'TEST99999');
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    app(MetaConversionsClient::class)->sendPurchase(makeOrder());

    Http::assertSent(function ($request) {
        expect($request->data()['test_event_code'])->toBe('TEST99999');

        return true;
    });
});

// ─── sendLead ─────────────────────────────────────────────────────────────────

it('sends a Lead event with hashed email only', function () {
    metaConfig();
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    $submission = makeSubmission(consentMarketing: true);

    app(MetaConversionsClient::class)->sendLead($submission, 'lead-7');

    Http::assertSent(function ($request) {
        $event = $request->data()['data'][0];
        expect($event['event_name'])->toBe('Lead');
        expect($event['event_id'])->toBe('lead-7');

        $userData = $event['user_data'];
        expect($userData['em'])->toBe(hash('sha256', 'anna@example.com'));
        // Lead sends email only — no phone/name fields
        expect($userData)->not->toHaveKey('ph');
        expect($userData)->not->toHaveKey('fn');

        return true;
    });
});

it('skips Lead when consent_marketing is false', function () {
    metaConfig();
    Http::fake();

    app(MetaConversionsClient::class)->sendLead(makeSubmission(consentMarketing: false), 'lead-7');

    Http::assertNothingSent();
});
