<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
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
     * comes from backoffice over /api/admin/cms/* and `pim:sync-full`,
     * and the seed factories use fakerphp/faker which is a dev dependency
     * (not present after `composer install --no-dev`).
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->info('Skipping seed — non-local environment.');

            return;
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(CmsSeeder::class);
    }
}
