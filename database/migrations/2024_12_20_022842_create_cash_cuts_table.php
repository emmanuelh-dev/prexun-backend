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
        Schema::create('cash_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->decimal('real_amount', 10, 2)->nullable();
            $table->date('date');
            $table->unsignedBigInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_cuts');
    }
};
