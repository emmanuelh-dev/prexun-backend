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
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('semana_intensiva_id')->nullable();

            // Add the foreign key constraint
            $table->foreign('semana_intensiva_id')
                  ->references('id')
                  ->on('semanas_intensivas')
                  ->onDelete('set null'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['semana_intensiva_id']);
            $table->dropColumn('semana_intensiva_id');
        });
    }
};