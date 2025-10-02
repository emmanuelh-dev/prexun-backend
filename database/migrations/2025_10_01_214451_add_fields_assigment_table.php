<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_assignments', function (Blueprint $table) {
            $table->foreignId('carrer_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('carreers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrer_id');
        });
    }
};