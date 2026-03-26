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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('specialization');
            $table->decimal('consultation_fee', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Look up all active doctors for a clinic filtered by specialization
            $table->index(['clinic_id', 'specialization']);
            $table->index(['clinic_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
