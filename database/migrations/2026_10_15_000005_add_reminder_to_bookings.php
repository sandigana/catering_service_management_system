<?php
// database/migrations/2026_10_15_000005_add_reminder_to_bookings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Remembers that the reminder went out, so it is never sent twice.
    public function up(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->timestamp('reminder_sent_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('reminder_sent_at'));
    }
};
