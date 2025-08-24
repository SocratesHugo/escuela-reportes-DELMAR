<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('grade_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_id')->constrained('works')->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Estado del trabajo para el alumno
            $t->enum('status', ['normal','P','J'])->default('normal');
            // Calificación capturada (solo aplica a 'normal')
            $t->decimal('score', 4, 2)->nullable(); // 0–10; nullable si es P o J

            $t->timestamps();

            $t->unique(['work_id','student_id']); // una fila por alumno x trabajo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_entries');
    }
};
