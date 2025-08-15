<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_assignments', function (Blueprint $table) {
            // First, temporarily disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            try {
                // Drop the unique constraint
                $table->dropUnique('unique_student_assignment');
            } catch (\Exception $e) {
                // If the constraint doesn't exist or can't be dropped, continue
                Log::warning('Could not drop unique constraint: ' . $e->getMessage());
            }
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_assignments', function (Blueprint $table) {
            // Only add the constraint back if it doesn't already exist
            try {
                $table->unique(['student_id', 'period_id', 'grupo_id', 'semana_intensiva_id'], 'unique_student_assignment');
            } catch (\Exception $e) {
                Log::warning('Could not recreate unique constraint: ' . $e->getMessage());
            }
        });
    }
};
