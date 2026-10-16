<?php
// database/migrations/2026_09_20_000002_add_is_active_and_soft_deletes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Accounts can be deactivated; users/bookings/payments are soft-deleted so
    // removing one never cascades away financial history.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
            $table->softDeletes();
        });

        Schema::table('bookings', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('payments', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('bookings', fn (Blueprint $table) => $table->dropSoftDeletes());

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
        });
    }
};
