<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('works', function (Blueprint $t) {
            $t->id();
            // assignment = materia↔grupo↔maestro
            $t->foreignId('group_subject_teacher_id')->constrained()->cascadeOnDelete();

            // semana (ya pertenece a un trimestre y ciclo)
            $t->foreignId('week_id')->constrained()->cascadeOnDelete();

            $t->string('name');                             // nombre del trabajo
            $t->enum('weekday', ['mon','tue','wed','thu','fri'])->nullable();  // día que se dejó
            $t->boolean('active')->default(true);
            $t->timestamps();

            // Evita duplicar el mismo nombre de trabajo en la misma semana para ese assignment
            $t->unique(['group_subject_teacher_id','week_id','name'], 'works_unique_name_per_assignment_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};

