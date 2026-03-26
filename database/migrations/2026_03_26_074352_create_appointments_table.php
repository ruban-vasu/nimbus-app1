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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();         // optional patient/doctor notes
            $table->timestamps();
            $table->softDeletes();

            // Enforce one booking per slot at the database level
            $table->unique('slot_id');

            // Get all appointments for a patient filtered by status
            $table->index(['patient_id', 'status'], 'appointments_patient_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
