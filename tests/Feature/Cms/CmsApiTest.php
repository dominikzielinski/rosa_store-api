<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Cms\Models\FaqItem;
use Modules\Cms\Models\SiteSetting;
use Modules\Cms\Models\Testimonial;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ─── Site Settings ────────────────────────────────────────────────────────

it('GET /api/settings auto-creates singleton on first call', function () {
    expect(SiteSetting::count())->toBe(0);

    getJson('/api/settings')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'contact' => ['email', 'phone', 'phoneHref', 'address', 'hours'],
                'social' => ['facebook', 'instagram', 'linkedin'],
                'hero' => ['heroVideoUrl'],
            ],
        ]);

    expect(SiteSetting::count())->toBe(1);
});

it('returns stored site settings', function () {
    SiteSetting::create([
        'contact_email' => 'test@rosadoro.pl',
        'contact_phone' => '+48 123 456 789',
        'contact_address' => 'Warszawa',
    ]);

    getJson('/api/settings')
        ->assertOk()
        ->assertJsonPath('data.contact.email', 'test@rosadoro.pl')
        ->assertJsonPath('data.contact.phone', '+48 123 456 789')
        ->assertJsonPath('data.contact.address', 'Warszawa');
});

it('caches site settings and invalidates on update', function () {
    $s = SiteSetting::create(['contact_email' => 'old@example.com']);

    // Populate cache
    getJson('/api/settings')->assertJsonPath('data.contact.email', 'old@example.com');
    expect(Cache::has(SiteSetting::CACHE_KEY))->toBeTrue();

    // Update via Eloquent — observer should flush the cache
    $s->update(['contact_email' => 'new@example.com']);

    getJson('/api/settings')->assertJsonPath('data.contact.email', 'new@example.com');
});

// ─── FAQ ──────────────────────────────────────────────────────────────────

it('GET /api/faq returns active items ordered', function () {
    FaqItem::create(['slug' => 'c', 'question' => 'Q3?', 'answer' => 'A3', 'sort_order' => 30, 'active' => true]);
    FaqItem::create(['slug' => 'a', 'question' => 'Q1?', 'answer' => 'A1', 'sort_order' => 10, 'active' => true]);
    FaqItem::create(['slug' => 'b', 'question' => 'Q2?', 'answer' => 'A2', 'sort_order' => 20, 'active' => true]);

    $response = getJson('/api/faq')->assertOk();

    expect(collect($response->json('data'))->pluck('slug')->all())
        ->toEqual(['a', 'b', 'c']);
});

it('skips inactive FAQ items', function () {
    FaqItem::create(['slug' => 'visible', 'question' => 'Q1?', 'answer' => 'A1', 'active' => true]);
    FaqItem::create(['slug' => 'hidden', 'question' => 'Q2?', 'answer' => 'A2', 'active' => false]);

    getJson('/api/faq')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'visible');
});

it('invalidates FAQ cache on update', function () {
    $item = FaqItem::create(['slug' => 'x', 'question' => 'Old?', 'answer' => 'Old', 'active' => true]);
    getJson('/api/faq')->assertJsonPath('data.0.question', 'Old?');

    $item->update(['question' => 'New?']);

    getJson('/api/faq')->assertJsonPath('data.0.question', 'New?');
});

// ─── Testimonials ─────────────────────────────────────────────────────────

it('GET /api/testimonials returns active testimonials', function () {
    Testimonial::create(['author_name' => 'Anna', 'content' => 'Great!', 'rating' => 5, 'active' => true]);
    Testimonial::create(['author_name' => 'Bad', 'content' => '...', 'active' => false]);

    getJson('/api/testimonials')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.authorName', 'Anna')
        ->assertJsonPath('data.0.rating', 5);
});

it('returns empty array when no active testimonials', function () {
    Testimonial::create(['author_name' => 'X', 'content' => '...', 'active' => false]);

    getJson('/api/testimonials')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('invalidates testimonial cache on update', function () {
    $t = Testimonial::create(['author_name' => 'Anna', 'content' => 'Old', 'active' => true]);
    getJson('/api/testimonials')->assertJsonPath('data.0.content', 'Old');

    $t->update(['content' => 'Updated']);

    getJson('/api/testimonials')->assertJsonPath('data.0.content', 'Updated');
});
