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

#[Description('Lista zgłoszeń kontaktowych z bazy danych. Opcjonalne filtry: typ (b2b/retail), data (liczba dni wstecz), limit.')]
class ListContactSubmissionsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $query = ContactSubmission::query()->latest();

        $rawType = $request->get('type');
        if (is_string($rawType) && $rawType !== '') {
            $type = ContactSubmissionTypeEnum::tryFrom($rawType);
            if ($type === null) {
                return Response::error("Nieprawidłowy typ: '{$rawType}'. Dozwolone: b2b, retail.");
            }
            $query->where('type', $type->value);
        }

        if ($days = $request->integer('days')) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        $limit = max(1, min($request->integer('limit', 20), 100));

        $submissions = $query->limit($limit)->get();

        if ($submissions->isEmpty()) {
            return Response::text('Brak zgłoszeń spełniających kryteria.');
        }

        $rows = $submissions->map(fn ($s) => sprintf(
            '#%d · %s · %s <%s> · %s · %s',
            $s->id,
            $s->type->getData()['name'],
            $s->full_name,
            $s->email,
            mb_substr($s->message, 0, 80).(mb_strlen($s->message) > 80 ? '…' : ''),
            $s->created_at->format('Y-m-d H:i'),
        ))->implode("\n");

        return Response::text("Znaleziono {$submissions->count()} zgłoszeń:\n\n{$rows}");
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        // All fields optional by default in Laravel JsonSchema — mark with ->required() only when needed.
        return [
            'type' => $schema->string()
                ->description('Filtruj po typie: "b2b" lub "retail".')
                ->enum(array_map(fn ($c) => $c->value, ContactSubmissionTypeEnum::cases())),
            'days' => $schema->integer()
                ->description('Zgłoszenia z ostatnich N dni.'),
            'limit' => $schema->integer()
                ->description('Maksymalna liczba wyników (domyślnie 20, max 100).'),
        ];
    }
}
