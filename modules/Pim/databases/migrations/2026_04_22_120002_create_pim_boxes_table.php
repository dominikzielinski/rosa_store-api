<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pim_boxes', function (Blueprint $table): void {
            $table->increments('id');

            // Stable backoffice-generated ID — upsert key for /api/admin/* sync.
            $table->unsignedBigInteger('backoffice_id')->nullable()->unique();

            $table->unsignedInteger('package_id');
            $table->foreign('package_id')
                ->references('id')->on('pim_packages')
                ->cascadeOnDelete();

            // Slug unique within the whole catalog — used as cart item key on FE
            // e.g. "premium-women-1"
            $table->string('slug', 100)->unique();

            // "women" / "men" — stored as string to stay schema-friendly for backoffice
            $table->string('gender', 10);

            $table->string('name', 255);
            $table->text('description')->nullable();

            // Optional override — null means "use package price"
            $table->unsignedInteger('price_pln')->nullable();
            $table->unsignedInteger('price_eur')->nullable();
            $table->unsignedInteger('price_usd')->nullable();

            // Single hero image per box (CDN URL from backoffice)
            $table->string('image_url', 500)->nullable();

            // Sellable right now? Backoffice can toggle independently of `active`
            // (e.g. product out of stock but should still be visible with "coming soon").
            $table->boolean('available')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['package_id', 'gender', 'sort_order']);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pim_boxes');
    }
};
