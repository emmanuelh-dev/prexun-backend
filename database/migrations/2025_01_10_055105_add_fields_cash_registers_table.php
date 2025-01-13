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
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->json('initial_amount_cash')->nullable();
            $table->json('final_amount_cash')->nullable();
            $table->decimal('next_day', 10, 2)->nullable();
            $table->json('next_day_cash')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('initial_amount_cash');
            $table->dropColumn('final_amount_cash');
            $table->dropColumn('next_day_cash');
            $table->dropColumn('next_day');
        });
    }
};
