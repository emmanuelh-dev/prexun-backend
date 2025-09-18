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
        Schema::table('student_assignments', function (Blueprint $table) {
            $table->boolean('book_delivered')->default(false)->after('notes');
            $table->enum('book_delivery_type', ['digital', 'fisico', 'paqueteria'])->nullable()->after('book_delivered');
            $table->date('book_delivery_date')->nullable()->after('book_delivery_type');
            $table->text('book_notes')->nullable()->after('book_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_assignments', function (Blueprint $table) {
            $table->dropColumn(['book_delivered', 'book_delivery_type', 'book_delivery_date', 'book_notes']);
        });
    }
};
