<?php
// database/migrations/2026_10_15_000002_create_reschedule_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A customer asks for a new date/time; back office approves or rejects it.
    public function up(): void
    {
        Schema::create('reschedule_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->date('requested_date');
            $table->time('requested_time')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending')->index();   // pending | approved | rejected | withdrawn
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('admin_note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reschedule_requests');
    }
};
