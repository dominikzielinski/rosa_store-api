<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->increments('id');

            // RD-XXXXXXXX — public reference, used by FE on /dziekujemy and by backoffice as idempotency key
            $table->string('order_number', 20)->unique();

            // Lifecycle: accepted | pending_payment | paid | synced | cancelled | sync_failed
            $table->string('status', 30)->index();

            // transfer | p24
            $table->string('payment_method', 20);

            // Server-side computed total in grosze (integer). Never trust FE prices.
            $table->unsignedInteger('total_amount_pln');

            // ── Billing snapshot (immutable after creation) ──
            $table->string('billing_type', 15);  // individual | company
            $table->string('billing_first_name', 255)->nullable();
            $table->string('billing_last_name', 255)->nullable();
            $table->string('billing_company', 255)->nullable();
            $table->string('billing_nip', 10)->nullable();
            $table->string('billing_email', 255);
            $table->string('billing_phone', 30);
            $table->string('billing_street', 255);
            $table->string('billing_house_number', 32);
            $table->string('billing_postal_code', 10);
            $table->string('billing_city', 100);

            $table->text('note')->nullable();

            $table->boolean('consent_terms');
            $table->boolean('consent_marketing')->default(false);

            // ── P24 fields (nullable when method=transfer) ──
            $table->string('p24_session_id', 100)->nullable()->unique();
            $table->string('p24_token', 100)->nullable();
            $table->unsignedBigInteger('p24_order_id')->nullable();
            $table->timestamp('p24_paid_at')->nullable();

            // ── Backoffice sync state ──
            $table->timestamp('backoffice_synced_at')->nullable();
            $table->string('backoffice_order_id', 50)->nullable();
            $table->unsignedTinyInteger('backoffice_sync_attempts')->default(0);
            $table->text('backoffice_last_error')->nullable();
            // Discriminator: which paymentStatus the order was last pushed with.
            // Lets the upgrade pending→paid push happen exactly once when P24
            // confirms payment; identical re-pushes are skipped.
            $table->string('backoffice_pushed_status', 16)->nullable();

            // Audit metadata
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
            $table->index(['status', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
