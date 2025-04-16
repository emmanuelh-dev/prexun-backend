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
        Schema::table('grupos', function (Blueprint $table) {
            // Modificar plantel_id para que sea nullable
            $table->unsignedBigInteger('plantel_id')->nullable()->change();
            
            // Añadir columna moodle_id para almacenar el ID del cohort en Moodle
            $table->unsignedBigInteger('moodle_id')->nullable()->after('end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            // Revertir plantel_id a not nullable
            $table->unsignedBigInteger('plantel_id')->nullable(false)->change();
            
            // Eliminar columna moodle_id
            $table->dropColumn('moodle_id');
        });
    }
};