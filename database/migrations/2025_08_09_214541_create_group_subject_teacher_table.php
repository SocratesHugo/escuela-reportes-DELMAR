<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_subject_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();     // grupo (7A, 7B…)
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();   // materia (por grado/ciclo)
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete(); // usuario con rol maestro
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Evita duplicar la misma materia en el mismo grupo
            $table->unique(['group_id','subject_id'], 'grp_subj_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_subject_teacher');
    }
};
