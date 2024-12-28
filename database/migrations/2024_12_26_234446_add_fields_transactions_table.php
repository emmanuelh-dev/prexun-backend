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
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('paid')->default(false);
            $table->date('payment_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('payment_method')->nullable()->change();
            $table->json('denominations')->nullable()->change();
            $table->string('transaction_type')->nullable()->change();
            $table->text('notes')->nullable()->change();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'paid',
                'payment_date',
                'expiration_date',
                'payment_method',
                'denominations',
                'transaction_type',
                'notes'
            ]);
        });
    }
};
