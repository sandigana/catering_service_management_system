<?php
// database/migrations/2026_09_20_000005_add_performance_indexes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Columns the lists, dashboard and availability checks filter on.
    public function up(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->index(['status', 'event_date']));
        Schema::table('payments', fn (Blueprint $table) => $table->index(['booking_id', 'status']));
        Schema::table('activity_logs', fn (Blueprint $table) => $table->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex(['booking_id', 'status']));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropIndex(['status', 'event_date']));
    }
};
