<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // char(36) — UUIDs are fixed-length, no length-prefix overhead.
            // Nullable — field is optional; existing orders and clients that omit it stay compatible.
            // No index — written once, read once by the CAPI job via order PK lookup.
            $table->char('meta_event_id', 36)->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('meta_event_id');
        });
    }
};
