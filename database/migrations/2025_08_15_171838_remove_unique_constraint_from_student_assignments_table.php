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
        // Use raw SQL to handle the constraint removal more safely
        try {
            DB::statement('ALTER TABLE student_assignments DROP INDEX unique_student_assignment');
            Log::info('Successfully dropped unique_student_assignment constraint');
        } catch (\Exception $e) {
            // If the constraint doesn't exist, that's fine - it means it was already removed
            Log::warning('Could not drop unique constraint (may not exist): ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only add the constraint back if it doesn't already exist
        try {
            DB::statement('ALTER TABLE student_assignments ADD UNIQUE unique_student_assignment (student_id, period_id, grupo_id, semana_intensiva_id)');
            Log::info('Successfully recreated unique_student_assignment constraint');
        } catch (\Exception $e) {
            Log::warning('Could not recreate unique constraint (may already exist): ' . $e->getMessage());
        }
    }
};
