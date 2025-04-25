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
        Schema::table('modulos', function (Blueprint $table) {
            // Añadir columna moodle_id para almacenar el ID de Moodle
            $table->unsignedBigInteger('moodle_id')->nullable()->after('id'); // O después de la columna que prefieras
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            // Eliminar columna moodle_id
            $table->dropColumn('moodle_id');
        });
    }
};