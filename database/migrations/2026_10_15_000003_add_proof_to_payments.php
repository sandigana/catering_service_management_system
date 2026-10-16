<?php
// database/migrations/2026_10_15_000003_add_proof_to_payments.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Customers upload a GCash/bank slip; the file lives on the PRIVATE disk.
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('reference_number');
            $table->boolean('submitted_by_customer')->default(false)->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['proof_path', 'submitted_by_customer']));
    }
};
