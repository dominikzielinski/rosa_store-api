<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Contact\Mail\ClientConfirmationMail;
use Modules\Contact\Mail\TeamNotificationMail;
use Modules\Contact\Models\ContactSubmission;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    config()->set('contact.notification_email', 'team@rosadoro.pl');
});

it('accepts a B2B submission and returns 201', function () {
    $response = postJson('/api/contact', [
        'type' => 'b2b',
        'fullName' => 'Jan Kowalski',
        'email' => 'jan@company.pl',
        'phone' => '500600700',
        'company' => 'Acme Sp. z o.o.',
        'nip' => '1234567890',
        'eventType' => 'Konferencja',
        'giftCount' => '50',
        'preferredContact' => 'phone',
        'message' => 'Interesuje mnie zamówienie na 50 pakietów Premium dla klientów.',
        'consentData' => true,
        'consentMarketing' => true,
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['message']);

    expect(ContactSubmission::count())->toBe(1);
    $row = ContactSubmission::first();
    expect($row->type->value)->toBe('b2b');
    expect($row->full_name)->toBe('Jan Kowalski');
});

it('accepts a retail submission with minimal fields', function () {
    $response = postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Anna Nowak',
        'email' => 'anna@example.com',
        'message' => 'Chciałabym dopytać o możliwość personalizacji boxu.',
        'consentData' => true,
    ]);

    $response->assertCreated();
    expect(ContactSubmission::first()->type->value)->toBe('retail');
});

it('queues team + client mails after storing', function () {
    postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Anna Nowak',
        'email' => 'anna@example.com',
        'message' => 'Wiadomość testowa spełniająca minimum 10 znaków.',
        'consentData' => true,
    ]);

    Mail::assertQueued(TeamNotificationMail::class);
    Mail::assertQueued(ClientConfirmationMail::class, fn ($mail) => $mail->hasTo('anna@example.com'));
});

it('normalizes email to lowercase and strips NIP formatting', function () {
    postJson('/api/contact', [
        'type' => 'b2b',
        'fullName' => 'Test User',
        'email' => 'UPPER@Example.Com',
        'company' => 'Test Co',
        'nip' => '123-456-78-90',
        'message' => 'Test message content here.',
        'consentData' => true,
    ]);

    $submission = ContactSubmission::first();
    expect($submission->email)->toBe('upper@example.com');
    expect($submission->nip)->toBe('1234567890');
});

it('rejects invalid type', function () {
    postJson('/api/contact', [
        'type' => 'invalid',
        'fullName' => 'Test',
        'email' => 'test@example.com',
        'message' => 'Test message content here.',
        'consentData' => true,
    ])->assertStatus(422);
});

it('rejects missing required fields', function () {
    postJson('/api/contact', [])->assertStatus(422);
});

it('rejects invalid email format', function () {
    postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Test',
        'email' => 'not-an-email',
        'message' => 'Test message content here.',
        'consentData' => true,
    ])->assertJsonValidationErrors(['email']);
});

it('rejects too short message', function () {
    postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Test',
        'email' => 'test@example.com',
        'message' => 'too short',
        'consentData' => true,
    ])->assertJsonValidationErrors(['message']);
});

it('rejects NIP that is not 10 digits', function () {
    postJson('/api/contact', [
        'type' => 'b2b',
        'fullName' => 'Test',
        'email' => 'test@example.com',
        'nip' => '123',
        'message' => 'Test message content here.',
        'consentData' => true,
    ])->assertJsonValidationErrors(['nip']);
});

it('rejects submission without consentData', function () {
    postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Test',
        'email' => 'test@example.com',
        'message' => 'Test message content here.',
        'consentData' => false,
    ])->assertJsonValidationErrors(['consentData']);
});

it('silently accepts honeypot-filled submissions without persisting or mailing', function () {
    Mail::fake();

    postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Bot',
        'email' => 'bot@example.com',
        'message' => 'Test message content here.',
        'consentData' => true,
        'website' => 'http://spam.example.com',
    ])->assertCreated();

    // Bot gets fake-success — no row, no mail
    expect(ContactSubmission::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('records IP and user-agent', function () {
    postJson('/api/contact', [
        'type' => 'retail',
        'fullName' => 'Test',
        'email' => 'test@example.com',
        'message' => 'Test message content here.',
        'consentData' => true,
    ], ['User-Agent' => 'RosaTest/1.0']);

    $submission = ContactSubmission::first();
    expect($submission->user_agent)->toBe('RosaTest/1.0');
    expect($submission->ip_address)->not->toBeNull();
});
