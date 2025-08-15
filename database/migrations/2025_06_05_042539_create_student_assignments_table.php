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
        Schema::create('student_assignments', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to students table
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            
            // Foreign key to periods table
            $table->foreignId('period_id')->constrained('periods')->onDelete('cascade');
            
            // Foreign key to grupos table (nullable in case student is not assigned to a group)
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->onDelete('set null');
            
            // Foreign key to semanas_intensivas table (nullable in case student is not in intensive week)
            $table->foreignId('semana_intensiva_id')->nullable()->constrained('semanas_intensivas')->onDelete('set null');
            
            // Additional fields for tracking
            $table->date('assigned_at')->default(now()); // When the assignment was made
            $table->date('valid_until')->nullable(); // Until when this assignment is valid
            $table->boolean('is_active')->default(true); // Whether this assignment is currently active
            $table->text('notes')->nullable(); // Any additional notes about the assignment
            
            $table->timestamps();  
          });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_assignments');
    }
};
