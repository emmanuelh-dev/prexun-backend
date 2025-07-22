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
            $table->string('whatsapp_id')->unique(); // ID único del usuario de WhatsApp
            $table->text('instructions')->nullable(); // Instrucciones para el comportamiento del bot
            $table->json('user_info')->nullable(); // Información básica del usuario (nombre, preferencias)
            $table->string('current_state')->default('idle'); // Estado actual: idle, waiting_response, etc
            $table->json('temp_data')->nullable(); // Datos temporales para flujos específicos
            $table->timestamp('last_interaction')->nullable(); // Última vez que interactuó
            $table->boolean('is_active')->default(true); // Si el contexto está activo
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index(['is_active']);
            $table->index(['last_interaction']);
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
