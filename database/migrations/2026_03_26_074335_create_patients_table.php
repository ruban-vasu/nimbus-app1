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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();      // unique login / contact identifier
            $table->string('phone', 20);
            $table->date('date_of_birth');
            $table->string('insurance_provider')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');                // look up patient by phone number
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
