<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Contact\Models\ContactSubmission;

#[Description('Pełne szczegóły pojedynczego zgłoszenia kontaktowego po ID.')]
class GetContactSubmissionTool extends Tool
{
    public function handle(Request $request): Response
    {
        $id = $request->integer('id');

        if ($id <= 0) {
            return Response::error('Pole "id" jest wymagane i musi być dodatnią liczbą całkowitą.');
        }

        $submission = ContactSubmission::find($id);

        if (! $submission) {
            return Response::error("Zgłoszenie #{$id} nie istnieje.");
        }

        $lines = [
            "ID: #{$submission->id}",
            "Typ: {$submission->type->getData()['name']}",
            "Imię i nazwisko: {$submission->full_name}",
            "E-mail: {$submission->email}",
            $submission->phone ? "Telefon: {$submission->phone}" : null,
            $submission->company ? "Firma: {$submission->company}" : null,
            $submission->nip ? "NIP: {$submission->nip}" : null,
            $submission->event_type ? "Typ eventu: {$submission->event_type}" : null,
            $submission->gift_count ? "Liczba prezentów: {$submission->gift_count}" : null,
            $submission->preferred_contact ? "Preferowany kontakt: {$submission->preferred_contact->getData()['name']}" : null,
            'Zgoda marketingowa: '.($submission->consent_marketing ? 'tak' : 'nie'),
            'IP: '.($submission->ip_address ?? '—'),
            "Utworzono: {$submission->created_at->format('Y-m-d H:i:s')}",
            '',
            '--- Wiadomość ---',
            $submission->message,
        ];

        return Response::text(implode("\n", array_filter($lines, fn ($line) => $line !== null)));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID zgłoszenia kontaktowego.')
                ->required(),
        ];
    }
}
