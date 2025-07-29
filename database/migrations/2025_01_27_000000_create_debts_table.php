<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('period_id')->constrained()->onDelete('cascade');
            $table->string('concept'); // Concepto del adeudo (ej: "Colegiatura Enero 2025")
            $table->decimal('total_amount', 10, 2); // Monto total del adeudo
            $table->decimal('paid_amount', 10, 2)->default(0); // Monto pagado
            $table->decimal('remaining_amount', 10, 2); // Monto restante
            $table->date('due_date'); // Fecha de vencimiento
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->text('description')->nullable(); // Descripción adicional
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index(['student_id', 'period_id']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('debts');
    }
};