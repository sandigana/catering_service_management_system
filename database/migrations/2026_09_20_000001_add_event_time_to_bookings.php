<?php
// database/migrations/2026_09_20_000001_add_event_time_to_bookings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Bookings need a start time so venue/time conflicts can be detected.
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->time('event_time')->nullable()->after('event_date');
            $table->index('event_date');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['event_date']);
            $table->dropColumn('event_time');
        });
    }
};
