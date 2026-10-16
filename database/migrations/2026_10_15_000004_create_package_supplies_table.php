<?php
// database/migrations/2026_10_15_000004_create_package_supplies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // "Per guest" recipe of a package, used to reserve stock for an event automatically.
    public function up(): void
    {
        Schema::create('package_supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_per_guest', 10, 3);
            $table->timestamps();

            $table->unique(['package_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_supplies');
    }
};
