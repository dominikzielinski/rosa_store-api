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
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // CMS seed — local/testing only. In production, content is pushed
        // by backoffice via /api/admin/cms/* endpoints.
        if (app()->environment(['local', 'testing'])) {
            $this->call(CmsSeeder::class);
        }
    }
}
