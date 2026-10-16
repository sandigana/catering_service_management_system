<?php
// database/migrations/2026_10_15_000001_add_phone_to_users.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A phone number is needed for event-day contact and SMS reminders.
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('phone', 30)->nullable()->after('email'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('phone'));
    }
};
