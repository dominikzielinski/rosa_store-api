<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\ContactSubmissionStatsTool;
use App\Mcp\Tools\GetContactSubmissionTool;
use App\Mcp\Tools\ListContactSubmissionsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Rosa D\'oro Server')]
#[Version('0.1.0')]
#[Instructions(<<<'MD'
    MCP server dla sklepu Rosa D'oro. Udostępnia tools do pracy ze zgłoszeniami kontaktowymi:

    - `list_contact_submissions` — lista zgłoszeń z filtrami (typ: b2b/retail, okres, limit)
    - `get_contact_submission` — pełne szczegóły pojedynczego zgłoszenia po ID
    - `contact_submission_stats` — statystyki: ogółem, podział na typy, ostatnie 7/30 dni

    Używaj do debugowania formularzy, analizy ruchu z B2B vs retail, sprawdzania zgłoszeń spamowych.
    MD)]
class RosaDOroServer extends Server
{
    /**
     * @var array<int, class-string>
     */
    protected array $tools = [
        ListContactSubmissionsTool::class,
        GetContactSubmissionTool::class,
        ContactSubmissionStatsTool::class,
    ];

    /**
     * @var array<int, class-string>
     */
    protected array $resources = [
        //
    ];

    /**
     * @var array<int, class-string>
     */
    protected array $prompts = [
        //
    ];
}
