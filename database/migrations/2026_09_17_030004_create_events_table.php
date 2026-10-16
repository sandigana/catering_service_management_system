<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name');
            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->string('location')->nullable();
            $table->string('event_type')->nullable();
            $table->unsignedInteger('guests')->default(1);
            $table->string('status')->default('upcoming');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
