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
        // Actualizar el campo conversation_type para permitir nuevos tipos
        Schema::table('chat_messages', function (Blueprint $table) {
            // Actualizar el valor por defecto para incluir más tipos
            $table->string('conversation_type')->default('general')->change();
        });
        
        // Los nuevos tipos que ahora soportamos:
        // - 'general': Conversaciones generales
        // - 'student_support': Soporte a estudiantes  
        // - 'test_evaluation': Evaluación de exámenes
        // - 'academic_guidance': Orientación académica
        // - 'whatsapp_outbound': Mensajes salientes de WhatsApp
        // - 'whatsapp_inbound': Mensajes entrantes de WhatsApp (para futuro)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En el rollback no necesitamos hacer nada especial
        // ya que solo estamos cambiando la lógica, no la estructura
    }
};
