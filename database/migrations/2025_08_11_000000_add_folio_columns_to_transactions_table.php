<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('folio_transfer')->nullable()->after('folio_new');
            $table->integer('folio_cash')->nullable()->after('folio_transfer');
            $table->integer('folio_card')->nullable()->after('folio_cash');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['folio_transfer', 'folio_cash', 'folio_card']);
        });
    }
};
