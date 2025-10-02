<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('student_assignments', 'carrer_id')) {
            Schema::table('student_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('carrer_id')->nullable()->after('id');
            });
        }

        Schema::table('student_assignments', function (Blueprint $table) {
            $table->foreign('carrer_id')
                  ->references('id')
                  ->on('carreers')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('student_assignments', function (Blueprint $table) {
            $table->dropForeign(['carrer_id']);
            $table->dropColumn('carrer_id');
        });
    }
};