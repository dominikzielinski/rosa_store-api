<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cms\Models\FaqItem;
use Modules\Cms\Models\SiteSetting;
use Modules\Cms\Models\Testimonial;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('backoffice.token', 'test-token');
});

function authed(): array
{
    return ['Authorization' => 'Bearer test-token'];
}

// ─── Settings ─────────────────────────────────────────────────────────────

it('PUT /api/admin/settings creates singleton if missing', function () {
    expect(SiteSetting::count())->toBe(0);

    putJson('/api/admin/settings', [
        'contactEmail' => 'new@rosadoro.pl',
        'contactPhone' => '+48 500 600 700',
    ], authed())
        ->assertOk()
        ->assertJsonPath('data.contact.email', 'new@rosadoro.pl')
        ->assertJsonPath('data.contact.phone', '+48 500 600 700');

    expect(SiteSetting::count())->toBe(1);
});

it('PUT /api/admin/settings merges — missing fields keep old values', function () {
    SiteSetting::create([
        'contact_email' => 'old@example.com',
        'contact_phone' => '+48 111 222 333',
    ]);

    putJson('/api/admin/settings', ['contactEmail' => 'new@example.com'], authed())
        ->assertOk()
        ->assertJsonPath('data.contact.email', 'new@example.com')
        ->assertJsonPath('data.contact.phone', '+48 111 222 333');  // preserved
});

// ─── FAQ ──────────────────────────────────────────────────────────────────

it('PUT /api/admin/faq/{id} creates new item', function () {
    putJson('/api/admin/faq/42', [
        'slug' => 'shipping',
        'question' => 'How long is shipping?',
        'answer' => '1-2 business days.',
        'category' => 'shipping',
        'sortOrder' => 10,
        'active' => true,
    ], authed())
        ->assertCreated()
        ->assertJsonPath('data.slug', 'shipping')
        ->assertJsonPath('data.question', 'How long is shipping?');

    expect(FaqItem::where('backoffice_id', 42)->exists())->toBeTrue();
});

it('PUT /api/admin/faq/{id} updates existing item', function () {
    $item = FaqItem::create([
        'backoffice_id' => 42,
        'question' => 'Old question',
        'answer' => 'Old answer',
    ]);

    putJson('/api/admin/faq/42', [
        'question' => 'Updated question',
        'answer' => 'Updated answer',
    ], authed())->assertOk();

    expect($item->fresh()->question)->toBe('Updated question');
});

it('DELETE /api/admin/faq/{id} removes item', function () {
    FaqItem::create(['backoffice_id' => 42, 'question' => 'Q?', 'answer' => 'A']);

    deleteJson('/api/admin/faq/42', [], authed())->assertNoContent();

    expect(FaqItem::where('backoffice_id', 42)->exists())->toBeFalse();
});

// ─── Testimonials ─────────────────────────────────────────────────────────

it('PUT /api/admin/testimonials/{id} creates testimonial', function () {
    putJson('/api/admin/testimonials/100', [
        'authorName' => 'Anna K.',
        'content' => 'Great!',
        'rating' => 5,
        'source' => 'retail',
        'active' => true,
    ], authed())->assertCreated();

    expect(Testimonial::where('backoffice_id', 100)->value('author_name'))->toBe('Anna K.');
});

it('invalidates public cache when admin upserts testimonial', function () {
    Testimonial::create([
        'backoffice_id' => 50,
        'author_name' => 'Old',
        'content' => 'Old content',
        'active' => true,
    ]);
    // Populate cache
    getJson('/api/testimonials')->assertJsonPath('data.0.authorName', 'Old');

    putJson('/api/admin/testimonials/50', [
        'authorName' => 'Updated',
        'content' => 'Updated content',
        'active' => true,
    ], authed())->assertOk();

    // Next public call sees the update immediately — observer cleared cache
    getJson('/api/testimonials')->assertJsonPath('data.0.authorName', 'Updated');
});

it('validates admin payload', function () {
    putJson('/api/admin/faq/42', ['question' => ''], authed())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['question', 'answer']);
});
