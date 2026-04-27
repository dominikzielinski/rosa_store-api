<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Initial import — seedy lokalne + pull stanu PIM z backoffice.
 *
 * Wywoływane po `migrate:fresh --force` przy świeżym deployu albo
 * gdy chcemy odbudować bazę od zera. Wszystkie kroki są idempotentne;
 * krok `pim:sync-full` jest tolerant na niedostępność backoffice
 * (loguje błąd i jedzie dalej — bez tego cały import by się sypnął
 * gdy BE jeszcze nie żyje).
 */
class ImportCommand extends Command
{
    protected $signature = 'import';

    protected $description = 'Run all initial imports (seed + pull from backoffice)';

    public function handle(): int
    {
        $time = microtime(true);

        $steps = [
            'db:seed' => true,           // hard-fail jeśli seed pęknie
            'pim:sync-full' => false,    // soft-fail (backoffice może być down)
        ];

        Schema::disableForeignKeyConstraints();

        foreach ($steps as $command => $hardFail) {
            $this->info("Running: {$command}");
            try {
                $this->call($command);
            } catch (Throwable $e) {
                $this->error("  -> {$command} failed: {$e->getMessage()}");
                if ($hardFail) {
                    Schema::enableForeignKeyConstraints();

                    return self::FAILURE;
                }
                $this->warn('  -> continuing (soft-fail step).');
            }
            $this->newLine();
        }

        Schema::enableForeignKeyConstraints();

        $this->newLine();
        $this->info('Time elapsed: '.round(microtime(true) - $time, 2).' seconds.');

        return self::SUCCESS;
    }
}
