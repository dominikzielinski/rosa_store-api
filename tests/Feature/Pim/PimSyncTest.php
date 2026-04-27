<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Modules\Pim\Models\Box;
use Modules\Pim\Models\Package;
use Modules\Pim\Sync\Jobs\SyncPimEntityJob;
use Modules\Pim\Sync\Services\PimFeedClient;
use Modules\Pim\Sync\Services\PimUpserter;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('backoffice.token', 'test-shared-token');
    config()->set('backoffice.url', 'https://backoffice.test');
    config()->set('backoffice.timeout', 5);
    config()->set('backoffice.paths.pim_packages', '/api/shop/pim/packages');
    config()->set('backoffice.paths.pim_package', '/api/shop/pim/packages/{id}');
    config()->set('backoffice.paths.pim_boxes', '/api/shop/pim/boxes');
    config()->set('backoffice.paths.pim_box', '/api/shop/pim/boxes/{id}');
});

// ─── Webhook auth + dispatch ──────────────────────────────────────────────

it('rejects panel notify without bearer token', function () {
    postJson('/api/panel/notify', ['entity' => 'package', 'id' => 5, 'action' => 'updated'])
        ->assertStatus(401);
});

it('rejects panel notify with wrong token', function () {
    postJson('/api/panel/notify',
        ['entity' => 'package', 'id' => 5, 'action' => 'updated'],
        ['Authorization' => 'Bearer not-the-real-token'],
    )->assertStatus(401);
});

it('queues sync job on valid signal', function () {
    Bus::fake();

    postJson('/api/panel/notify',
        ['entity' => 'box', 'id' => 42, 'action' => 'updated', 'occurredAt' => 1714076400],
        ['Authorization' => 'Bearer test-shared-token'],
    )->assertOk();

    Bus::assertDispatched(SyncPimEntityJob::class, fn ($job) => $job->entity === 'box'
        && $job->backofficeId === 42
        && $job->action === 'updated');
});

it('rejects invalid entity / action', function () {
    Bus::fake();

    postJson('/api/panel/notify',
        ['entity' => 'unicorn', 'id' => 1, 'action' => 'updated'],
        ['Authorization' => 'Bearer test-shared-token'],
    )->assertJsonValidationErrors(['entity']);

    postJson('/api/panel/notify',
        ['entity' => 'package', 'id' => 1, 'action' => 'exploded'],
        ['Authorization' => 'Bearer test-shared-token'],
    )->assertJsonValidationErrors(['action']);

    Bus::assertNotDispatched(SyncPimEntityJob::class);
});

it('no-ops on assortment signals (resolved by full sync)', function () {
    Bus::fake();

    postJson('/api/panel/notify',
        ['entity' => 'assortment', 'id' => 7, 'action' => 'updated'],
        ['Authorization' => 'Bearer test-shared-token'],
    )->assertOk();

    Bus::assertNotDispatched(SyncPimEntityJob::class);
});

// ─── PimUpserter ──────────────────────────────────────────────────────────

it('upserts a package by backoffice_id', function () {
    $upserter = app(PimUpserter::class);

    $upserter->upsert('package', [
        'id' => 100,
        'slug' => 'premium',
        'name' => 'Premium',
        'tagline' => 'Złoty środek',
        'description' => 'Opis',
        'prices' => [['currency' => ['code' => 'PLN'], 'amount' => 80000]],
        'highlighted' => true,
        'sortOrder' => 2,
    ]);

    $pkg = Package::where('backoffice_id', 100)->firstOrFail();
    expect($pkg->slug)->toBe('premium');
    expect($pkg->price_pln)->toBe(80000);
    expect($pkg->highlighted)->toBeTrue();
    expect($pkg->active)->toBeTrue();

    // Idempotent — second upsert updates in place
    $upserter->upsert('package', [
        'id' => 100,
        'slug' => 'premium',
        'name' => 'Premium 2',
        'prices' => [['currency' => ['code' => 'PLN'], 'amount' => 90000]],
    ]);

    expect(Package::count())->toBe(1);
    expect($pkg->fresh()->name)->toBe('Premium 2');
    expect($pkg->fresh()->price_pln)->toBe(90000);
});

it('marks package inactive on deleted action', function () {
    $upserter = app(PimUpserter::class);
    $upserter->upsert('package', [
        'id' => 200,
        'slug' => 'standard',
        'name' => 'Standard',
        'prices' => [['currency' => ['code' => 'PLN'], 'amount' => 35000]],
    ]);
    expect(Package::where('backoffice_id', 200)->value('active'))->toBeTrue();

    $upserter->markInactive('package', 200);

    expect(Package::where('backoffice_id', 200)->value('active'))->toBeFalse();
    // Row preserved for historical references
    expect(Package::where('backoffice_id', 200)->exists())->toBeTrue();
});

// ─── SyncPimEntityJob via PimFeedClient ───────────────────────────────────

it('syncs a package by pulling from feed when handled', function () {
    Http::fake([
        'backoffice.test/api/shop/pim/packages/300' => Http::response([
            'data' => [
                'id' => 300,
                'slug' => 'vip',
                'name' => 'VIP',
                'tagline' => 'Bez kompromisów',
                'prices' => [['currency' => ['code' => 'PLN'], 'amount' => 130000]],
                'highlighted' => false,
                'sortOrder' => 3,
            ],
        ]),
    ]);

    (new SyncPimEntityJob('package', 300, 'updated'))
        ->handle(app(PimFeedClient::class), app(PimUpserter::class));

    $pkg = Package::where('backoffice_id', 300)->firstOrFail();
    expect($pkg->name)->toBe('VIP');
    expect($pkg->price_pln)->toBe(130000);
});

it('marks package inactive when feed returns 404', function () {
    Http::fake([
        'backoffice.test/api/shop/pim/packages/404' => Http::response([], 404),
    ]);

    Package::create([
        'backoffice_id' => 404,
        'slug' => 'gone',
        'name' => 'Gone',
        'price_pln' => 1000,
        'active' => true,
    ]);

    (new SyncPimEntityJob('package', 404, 'updated'))
        ->handle(app(PimFeedClient::class), app(PimUpserter::class));

    expect(Package::where('backoffice_id', 404)->value('active'))->toBeFalse();
});

it('skips remote fetch on deleted action and just disables locally', function () {
    Http::fake(); // any call would fail loudly

    $pkg = Package::create([
        'backoffice_id' => 500,
        'slug' => 'discontinued',
        'name' => 'Discontinued',
        'price_pln' => 1000,
        'active' => true,
    ]);

    (new SyncPimEntityJob('package', 500, 'deleted'))
        ->handle(app(PimFeedClient::class), app(PimUpserter::class));

    expect($pkg->fresh()->active)->toBeFalse();
    Http::assertNothingSent();
});

it('upserts a box and resolves its parent package by backoffice_id', function () {
    $parent = Package::create([
        'backoffice_id' => 600,
        'slug' => 'standard',
        'name' => 'Standard',
        'price_pln' => 35000,
        'active' => true,
    ]);

    Http::fake([
        'backoffice.test/api/shop/pim/boxes/700' => Http::response([
            'data' => [
                'id' => 700,
                'slug' => 'standard-women-1',
                'name' => 'Spa w domu',
                'gender' => ['slug' => 'female'],
                'packageBackofficeId' => 600,
                'prices' => [['currency' => ['code' => 'PLN'], 'amount' => 35000]],
                'image' => 'https://cdn.example/box.webp',
                'available' => true,
                'sortOrder' => 1,
            ],
        ]),
    ]);

    (new SyncPimEntityJob('box', 700, 'created'))
        ->handle(app(PimFeedClient::class), app(PimUpserter::class));

    $box = Box::where('backoffice_id', 700)->firstOrFail();
    expect($box->package_id)->toBe($parent->id);
    expect($box->gender->value)->toBe('women');  // 'female' → 'women' mapping
    expect($box->image_url)->toBe('https://cdn.example/box.webp');
});
