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
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Clave única para identificar la configuración
            $table->string('label'); // Etiqueta legible para la interfaz
            $table->text('value')->nullable(); // Valor de la configuración (puede ser JSON)
            $table->string('type')->default('text'); // Tipo de input: text, number, boolean, select, json, etc.
            $table->text('description')->nullable(); // Descripción de qué hace esta configuración
            $table->json('options')->nullable(); // Opciones para selects u otros tipos
            $table->string('group')->default('general'); // Grupo para organizar configuraciones
            $table->integer('sort_order')->default(0); // Orden de visualización
            $table->boolean('is_active')->default(true); // Si está activa la configuración
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
