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
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('campus_id')->constrained();
            $table->text('notes')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
