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
        Schema::table('mensajes', function (Blueprint $table) {
            // Campo para identificar el número de teléfono del contacto
            $table->string('phone_number')->after('mensaje')->nullable();
            
            // Campo para identificar dirección del mensaje (sent/received)
            $table->enum('direction', ['sent', 'received'])->after('phone_number')->default('sent');
            
            // Campo para tipo de mensaje (text/template/image/etc)
            $table->string('message_type')->after('direction')->default('text');
            
            // Campo opcional para agrupar conversaciones
            $table->string('session_id')->after('message_type')->nullable();
            
            // Campo para el usuario que maneja la conversación (quien envía desde el sistema)
            $table->unsignedBigInteger('user_id')->after('session_id')->nullable();
            
            // Agregar índices para mejorar consultas
            $table->index(['phone_number', 'created_at']);
            $table->index(['session_id']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->dropIndex(['phone_number', 'created_at']);
            $table->dropIndex(['session_id']);
            $table->dropIndex(['user_id']);
            
            $table->dropColumn([
                'phone_number',
                'direction', 
                'message_type',
                'session_id',
                'user_id'
            ]);
        });
    }
};
