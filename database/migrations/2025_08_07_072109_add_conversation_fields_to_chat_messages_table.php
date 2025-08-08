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
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('conversation_type')->default('general')->after('metadata'); // 'general', 'student_support', 'test_evaluation', etc.
            $table->unsignedBigInteger('related_id')->nullable()->after('conversation_type'); // ID del estudiante, examen, etc.
            $table->string('session_id')->nullable()->after('related_id'); // Para agrupar conversaciones relacionadas
            
            // Índices para optimizar consultas
            $table->index(['conversation_type']);
            $table->index(['session_id']);
            $table->index(['related_id']);
            $table->index(['user_id', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_type']);
            $table->dropIndex(['session_id']);
            $table->dropIndex(['related_id']);
            $table->dropIndex(['user_id', 'session_id']);
            
            $table->dropColumn(['conversation_type', 'related_id', 'session_id']);
        });
    }
};
