<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('backoffice.token', 'test-token-0123456789abcdef');
});

it('rejects admin request without Authorization header', function () {
    putJson('/api/admin/settings', ['contactEmail' => 'x@x.pl'])
        ->assertStatus(401)
        ->assertJsonPath('message', 'Unauthorized.');
});

it('rejects admin request with wrong token', function () {
    putJson('/api/admin/settings', ['contactEmail' => 'x@x.pl'], [
        'Authorization' => 'Bearer wrong-token',
    ])->assertStatus(401);
});

it('rejects admin request with malformed Authorization header', function () {
    putJson('/api/admin/settings', ['contactEmail' => 'x@x.pl'], [
        'Authorization' => 'test-token-0123456789abcdef',  // missing "Bearer "
    ])->assertStatus(401);
});

it('accepts admin request with correct Bearer token', function () {
    putJson('/api/admin/settings', ['contactEmail' => 'ok@rosadoro.pl'], [
        'Authorization' => 'Bearer test-token-0123456789abcdef',
    ])->assertOk();
});

it('returns 503 when backoffice token is not configured', function () {
    config()->set('backoffice.token', null);

    putJson('/api/admin/settings', ['contactEmail' => 'x@x.pl'], [
        'Authorization' => 'Bearer whatever',
    ])->assertStatus(503);
});

it('accepts DELETE with correct token and is idempotent', function () {
    deleteJson('/api/admin/faq/9999', [], [
        'Authorization' => 'Bearer test-token-0123456789abcdef',
    ])->assertNoContent();
});
