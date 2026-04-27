<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pim_package_images', function (Blueprint $table): void {
            $table->increments('id');

            // Stable backoffice-generated ID — upsert key for /api/admin/* sync.
            $table->unsignedBigInteger('backoffice_id')->nullable()->unique();

            $table->unsignedInteger('package_id');
            $table->foreign('package_id')
                ->references('id')->on('pim_packages')
                ->cascadeOnDelete();

            // External CDN URL (files.rosa.dominikz.pl) — backoffice uploads, we just serve the URL
            $table->string('url', 500);
            $table->string('alt', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['package_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pim_package_images');
    }
};
