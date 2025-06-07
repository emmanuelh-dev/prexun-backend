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
        Schema::create('student_events', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to students table
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            
            // Foreign key to users table (who made the change)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Event type (created, updated, moved, deleted, restored, etc.)
            $table->string('event_type');
            
            // Description of what happened
            $table->text('description')->nullable();
            
            // JSON data of the student BEFORE the change
            $table->json('data_before')->nullable();
            
            // JSON data of the student AFTER the change
            $table->json('data_after')->nullable();
            
            // Specific fields that changed (for quick filtering)
            $table->json('changed_fields')->nullable();
            
            // IP address of who made the change
            $table->string('ip_address')->nullable();
            
            // User agent of who made the change
            $table->text('user_agent')->nullable();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['student_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_events');
    }
};
