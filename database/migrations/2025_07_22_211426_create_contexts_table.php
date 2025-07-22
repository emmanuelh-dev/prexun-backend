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
        Schema::create('contexts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Nombre de la instrucción
            $table->text('instructions'); // Instrucciones para ChatGPT
            $table->boolean('is_active')->default(true); // Si está activo
            $table->timestamps();
            
            // Índice para optimizar consultas
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contexts');
    }
};
