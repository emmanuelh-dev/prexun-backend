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
        Schema::table('debts', function (Blueprint $table) {
            $table->unsignedBigInteger('assignment_id')->nullable()->after('student_id');
            $table->foreign('assignment_id')->references('id')->on('student_assignments')->onDelete('set null');
            
            // Hacer period_id nullable para permitir transición
            $table->unsignedBigInteger('period_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropForeign(['assignment_id']);
            $table->dropColumn('assignment_id');
            
            // Restaurar period_id como requerido
            $table->unsignedBigInteger('period_id')->nullable(false)->change();
        });
    }
};
