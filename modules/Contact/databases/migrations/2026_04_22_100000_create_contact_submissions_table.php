<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->increments('id');

            // Submission type — distinguishes B2B from retail inquiries.
            $table->string('type', 10)->index();

            // Required contact data
            $table->string('full_name', 255);
            $table->string('email', 255);
            $table->text('message');

            // Optional — B2B specific
            $table->string('phone', 20)->nullable();
            $table->string('company', 255)->nullable();
            $table->string('nip', 10)->nullable();
            $table->string('event_type', 255)->nullable();
            $table->string('gift_count', 50)->nullable();
            $table->string('preferred_contact', 10)->nullable();

            // Consents (both booleans — consent_data required true, marketing optional)
            $table->boolean('consent_data')->default(false);
            $table->boolean('consent_marketing')->default(false);

            // Metadata for abuse detection and support
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
