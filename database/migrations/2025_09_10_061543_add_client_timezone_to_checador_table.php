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
        Schema::table('checador', function (Blueprint $table) {
            $table->string('client_timezone')->nullable()->after('is_complete_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checador', function (Blueprint $table) {
            $table->dropColumn('client_timezone');
        });
    }
};
