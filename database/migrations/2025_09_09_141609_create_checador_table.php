<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('work_date');
            $table->time('check_in_at')->nullable();
            $table->time('check_out_at')->nullable();
            $table->enum('status', ['present', 'on_break', 'back_from_break', 'checked_out', 'rest_day', 'absent'])->default('present');
            $table->time('break_start_at')->nullable();
            $table->time('break_end_at')->nullable();
            $table->integer('break_duration')->nullable()->comment('Duración del descanso en minutos');
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->boolean('is_complete_day')->default(false);
            $table->timestamps();
            
            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checador');
    }
};
