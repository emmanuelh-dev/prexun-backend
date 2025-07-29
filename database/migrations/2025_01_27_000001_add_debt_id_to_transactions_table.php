<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('debt_id')->nullable()->constrained()->onDelete('set null');
            $table->index('debt_id');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['debt_id']);
            $table->dropIndex(['debt_id']);
            $table->dropColumn('debt_id');
        });
    }
};