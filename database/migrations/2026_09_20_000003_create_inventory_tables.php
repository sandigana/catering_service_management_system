<?php
// database/migrations/2026_09_20_000003_create_inventory_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 30)->default('pcs');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('low_stock_threshold', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // Stock reserved for a specific event (deducted from inventory_items).
        Schema::create('event_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_inventory');
        Schema::dropIfExists('inventory_items');
    }
};
