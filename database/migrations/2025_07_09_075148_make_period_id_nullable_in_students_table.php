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
            // Drop the foreign key constraint first
            $table->dropForeign(['period_id']);
            
            // Make period_id nullable
            $table->unsignedBigInteger('period_id')->nullable()->change();
            
            // Re-add the foreign key constraint (allowing nulls)
            $table->foreign('period_id')->references('id')->on('periods')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['period_id']);
            
            // Make period_id not nullable again
            $table->unsignedBigInteger('period_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint (not allowing nulls)
            $table->foreign('period_id')->references('id')->on('periods');
        });
    }
};
