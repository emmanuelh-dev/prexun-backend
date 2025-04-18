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
        Schema::create('semana_intensivas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('Hibrido');
            $table->unsignedBigInteger('plantel_id')->nullable();
            $table->foreign('plantel_id')->references('id')->on('campuses')->onDelete('cascade');
            $table->unsignedBigInteger('period_id')->nullable();
            $table->foreign('period_id')->references('id')->on('periods')->onDelete('cascade');
            $table->integer('capacity');
            $table->json('frequency');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('moodle_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semana_intensivas');
    }
};
