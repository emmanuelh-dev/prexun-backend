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
            // Add columns first before modifying them
            $table->unsignedBigInteger('carrer_id')->nullable();
            $table->unsignedBigInteger('facultad_id')->nullable();
            $table->unsignedBigInteger('prepa_id')->nullable(); 
            $table->unsignedBigInteger('municipio_id')->nullable();
            
            // Tutor information
            $table->string('tutor_name')->nullable();
            $table->string('tutor_phone')->nullable();
            $table->string('tutor_relationship')->nullable();
            
            // Academic information
            $table->decimal('average', 5, 2)->nullable();
            $table->enum('attempts', ['1', '2', '3', '4', '5', 'NA'])->nullable();
            $table->integer('score')->nullable();
            
            // Additional information
            $table->text('health_conditions')->nullable();
            $table->string('how_found_out')->nullable();
            $table->string('preferred_communication')->nullable();
            
            $table->string('username')->nullable()->change();
            $table->string('status')->default('Activo');
            $table->foreign('carrer_id')->references('id')->on('carreers')->onDelete('set null');
            $table->foreign('facultad_id')->references('id')->on('facultades')->onDelete('set null');
            $table->foreign('prepa_id')->references('id')->on('prepas')->onDelete('set null');
            $table->foreign('municipio_id')->references('id')->on('municipios')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['carrer_id']);
            $table->dropForeign(['facultad_id']);
            $table->dropForeign(['prepa_id']);
            $table->dropForeign(['municipio_id']);
            
            $table->dropColumn([
                'carrer_id',
                'facultad_id', 
                'prepa_id',
                'municipio_id',
                'tutor_name',
                'tutor_phone',
                'tutor_relationship',
                'average',
                'attempts',
                'score',
                'health_conditions',
                'how_found_out',
                'preferred_communication',
                'status'
            ]);
        });
    }
};
