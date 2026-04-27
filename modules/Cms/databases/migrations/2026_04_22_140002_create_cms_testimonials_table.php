<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_testimonials', function (Blueprint $table): void {
            $table->increments('id');

            // Stable backoffice-generated ID — upsert key for /api/admin/* sync.
            $table->unsignedBigInteger('backoffice_id')->nullable()->unique();

            // Public identity — e.g. "Anna K.", "Tomasz W.", or just "Anna"
            $table->string('author_name', 120);
            // Internal note (full name, company) — never shown publicly
            $table->string('author_note', 255)->nullable();

            $table->text('content');

            // Optional star rating 1-5
            $table->unsignedTinyInteger('rating')->nullable();

            // "b2b" / "retail" — where the review came from (for filtering on FE)
            $table->string('source', 20)->nullable();

            // When the review was posted by the client (may differ from created_at)
            $table->date('posted_at')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_testimonials');
    }
};
