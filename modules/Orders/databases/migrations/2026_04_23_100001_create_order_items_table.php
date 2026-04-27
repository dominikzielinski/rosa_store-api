<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->increments('id');

            $table->unsignedInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();

            // Reference to the box at order time. No FK constraint — box may be soft-hidden
            // or removed in PIM later. We keep snapshots below for historical accuracy.
            $table->unsignedInteger('box_id');

            // Snapshot: name/slug/package_slug captured at order time. Backoffice updates
            // to PIM after the order do not affect history.
            $table->string('box_slug', 100);
            $table->string('box_name', 255);
            $table->string('package_slug', 50);
            $table->string('gender', 10);

            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_pln');   // grosze
            $table->unsignedInteger('total_price_pln');  // grosze, =quantity * unit_price

            $table->timestamps();

            $table->index(['order_id']);
            $table->index('box_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
