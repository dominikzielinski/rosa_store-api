<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pim\Enums\BoxGenderEnum;
use Modules\Pim\Models\Box;
use Modules\Pim\Models\Package;
use Modules\Pim\Models\PackageImage;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function makePackage(array $overrides = []): Package
{
    return Package::create(array_merge([
        'slug' => 'standard',
        'name' => 'Standard',
        'tagline' => 'Idealny na początek',
        'description' => 'Lorem ipsum',
        'price_pln' => 35000,
        'price_eur' => 8000,
        'price_usd' => 8800,
        'highlighted' => false,
        'sort_order' => 10,
        'active' => true,
    ], $overrides));
}

function makeBox(Package $package, array $overrides = []): Box
{
    return Box::create(array_merge([
        'package_id' => $package->id,
        'slug' => $package->slug.'-women-1',
        'gender' => BoxGenderEnum::Women->value,
        'name' => 'Box "Relaks"',
        'description' => 'Ziołowa herbata i świeca',
        'image_url' => 'https://files.rosa.dominikz.pl/example.webp',
        'available' => true,
        'sort_order' => 10,
        'active' => true,
    ], $overrides));
}

it('GET /api/packages returns active packages with boxes and images', function () {
    $pkg = makePackage();
    PackageImage::create(['package_id' => $pkg->id, 'url' => 'https://cdn.example.com/a.webp', 'alt' => 'A', 'sort_order' => 0]);
    makeBox($pkg);

    $response = getJson('/api/packages');

    $response->assertOk()
        ->assertJsonPath('data.0.slug', 'standard')
        ->assertJsonPath('data.0.name', 'Standard')
        ->assertJsonPath('data.0.prices.PLN', 35000)
        ->assertJsonPath('data.0.prices.EUR', 8000)
        ->assertJsonPath('data.0.images.0', 'https://cdn.example.com/a.webp')
        ->assertJsonPath('data.0.boxes.0.slug', 'standard-women-1')
        ->assertJsonPath('data.0.boxes.0.gender.slug', 'women');
});

it('skips inactive packages', function () {
    makePackage(['slug' => 'active', 'active' => true]);
    makePackage(['slug' => 'hidden', 'active' => false]);

    getJson('/api/packages')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'active');
});

it('skips inactive boxes within an active package', function () {
    $pkg = makePackage();
    makeBox($pkg, ['slug' => 'standard-women-1', 'active' => true]);
    makeBox($pkg, ['slug' => 'standard-women-2', 'active' => false]);

    getJson('/api/packages')
        ->assertOk()
        ->assertJsonCount(1, 'data.0.boxes')
        ->assertJsonPath('data.0.boxes.0.slug', 'standard-women-1');
});

it('orders packages by sort_order', function () {
    makePackage(['slug' => 'c', 'sort_order' => 30]);
    makePackage(['slug' => 'a', 'sort_order' => 10]);
    makePackage(['slug' => 'b', 'sort_order' => 20]);

    $response = getJson('/api/packages');

    expect(collect($response->json('data'))->pluck('slug')->all())
        ->toEqual(['a', 'b', 'c']);
});

it('box price falls back to package price when null', function () {
    $pkg = makePackage(['price_pln' => 35000, 'price_eur' => 8000]);
    makeBox($pkg, ['price_pln' => null, 'price_eur' => null]);

    getJson('/api/packages')
        ->assertJsonPath('data.0.boxes.0.prices.PLN', 35000)
        ->assertJsonPath('data.0.boxes.0.prices.EUR', 8000);
});

it('box price override beats package price', function () {
    $pkg = makePackage(['price_pln' => 35000]);
    makeBox($pkg, ['price_pln' => 99900]);

    getJson('/api/packages')
        ->assertJsonPath('data.0.boxes.0.prices.PLN', 99900);
});

it('GET /api/packages/{slug} returns a single package', function () {
    makePackage();

    getJson('/api/packages/standard')
        ->assertOk()
        ->assertJsonPath('data.slug', 'standard');
});

it('GET /api/packages/{slug} returns 404 for unknown slug', function () {
    getJson('/api/packages/nonexistent')->assertStatus(404);
});

it('GET /api/packages/{slug} returns 404 for inactive package', function () {
    makePackage(['slug' => 'hidden', 'active' => false]);

    getJson('/api/packages/hidden')->assertStatus(404);
});
