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
        Schema::create('campus_semana_intensiva_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained()->onDelete('cascade');
            $table->foreignId('semana_intensiva_id')->constrained('semanas_intensivas')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['campus_id', 'semana_intensiva_id'], 'campus_semana_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campus_semana_intensiva_pivot');
    }
};
