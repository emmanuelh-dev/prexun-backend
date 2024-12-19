<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromocionesTable extends Migration
{
    public function up()
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('regular_cost', 10, 2);
            $table->decimal('cost', 10, 2);
            $table->string('type');
            $table->dateTime('limit_date');
            $table->json('groups');
            $table->json('pagos');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('promociones');
    }
}