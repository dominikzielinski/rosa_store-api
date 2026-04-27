<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton table — always exactly one row. Stores globally used site data
 * (contact info, social links, hero video URL, etc.) that backoffice can edit
 * in one place, instead of having the frontend hardcode them in 10+ components.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->increments('id');

            // Contact
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_phone_href', 30)->nullable();  // e.g. "tel:+48500600700"
            $table->string('contact_address', 500)->nullable();
            $table->string('business_hours', 255)->nullable();

            // Social media
            $table->string('social_facebook', 500)->nullable();
            $table->string('social_instagram', 500)->nullable();
            $table->string('social_linkedin', 500)->nullable();

            // Hero / home page
            $table->string('hero_video_url', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
