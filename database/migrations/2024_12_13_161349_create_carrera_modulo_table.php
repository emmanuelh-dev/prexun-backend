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
        Schema::create('carrer_modulo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('carrer_id');
            $table->unsignedBigInteger('modulo_id');
            $table->foreign('carrer_id')->references('id')->on('carreers')->onDelete('cascade');
            $table->foreign('modulo_id')->references('id')->on('modulos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carrer_modulo');
    }
};