<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration');  // slot length in minutes
            $table->enum('status', ['available', 'booked', 'blocked'])->default('available');
            $table->timestamps();
            $table->softDeletes();

            // General schedule lookup: all slots for a doctor on a date
            $table->index(['doctor_id', 'date'], 'slots_doctor_date_idx');

            // Availability query: WHERE doctor_id = ? AND date = ? AND status = 'available'
            $table->index(['doctor_id', 'date', 'status'], 'slots_doctor_date_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
