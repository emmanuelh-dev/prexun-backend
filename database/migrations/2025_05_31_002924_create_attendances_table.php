<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->unsignedBigInteger('grupo_id');      // Grupo al que pertenece
            $table->unsignedBigInteger('student_id');    // Estudiante relacionado

            // Detalles de asistencia
            $table->date('fecha');                        // Fecha de la clase
            $table->string('status');                     // presente, ausente, tarde, etc.

            $table->timestamps();

            // Claves foráneas
            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');

            // Evitar duplicados
            $table->unique(['grupo_id', 'student_id', 'fecha']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
