<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Cms\databases\seeders\CmsSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Whole seed is local/testing only. Production never seeds — content
     * comes from backoffice over /api/admin/cms/* and `pim:sync-full`.
     *
     * No User seed — the shop has no auth, no login UI, no admin panel.
     * The User model + migration are kept as a placeholder for future
     * admin auth (see App\Models\User class docblock).
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->info('Skipping seed — non-local environment.');

            return;
        }

        $this->call(CmsSeeder::class);
    }
}
