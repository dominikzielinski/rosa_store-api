<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Modules\Contact\Enums\ContactSubmissionTypeEnum;
use Modules\Contact\Models\ContactSubmission;

#[Description('Statystyki zgłoszeń kontaktowych: ogółem, podział na typy, dziś/tydzień/miesiąc.')]
class ContactSubmissionStatsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $total = ContactSubmission::count();
        $b2b = ContactSubmission::where('type', ContactSubmissionTypeEnum::B2B->value)->count();
        $retail = ContactSubmission::where('type', ContactSubmissionTypeEnum::Retail->value)->count();
        $today = ContactSubmission::whereDate('created_at', today())->count();
        $week = ContactSubmission::where('created_at', '>=', now()->subDays(7))->count();
        $month = ContactSubmission::where('created_at', '>=', now()->subDays(30))->count();
        $marketingConsent = ContactSubmission::where('consent_marketing', true)->count();

        $lines = [
            '=== Statystyki zgłoszeń kontaktowych ===',
            '',
            "Ogółem: {$total}",
            "  • B2B: {$b2b}",
            "  • Retail: {$retail}",
            '',
            "Dzisiaj: {$today}",
            "Ostatnie 7 dni: {$week}",
            "Ostatnie 30 dni: {$month}",
            '',
            "Zgoda marketingowa: {$marketingConsent} z {$total}",
        ];

        return Response::text(implode("\n", $lines));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
