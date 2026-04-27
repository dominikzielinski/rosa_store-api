<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_faq_items', function (Blueprint $table): void {
            $table->increments('id');

            // Stable backoffice-generated ID — upsert key for /api/admin/* sync.
            $table->unsignedBigInteger('backoffice_id')->nullable()->unique();

            // Optional slug for deep-linking / anchor URLs
            $table->string('slug', 100)->unique()->nullable();

            $table->string('question', 500);
            $table->text('answer');

            // Optional category ("shipping", "payment", etc.) for future filtering
            $table->string('category', 50)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_faq_items');
    }
};
