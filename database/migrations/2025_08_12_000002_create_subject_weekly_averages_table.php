<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('subject_weekly_averages')) {
            Schema::create('subject_weekly_averages', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('group_subject_teacher_id');
                $table->unsignedBigInteger('week_id');

                $table->decimal('avg', 4, 2)->nullable(); // 0.00–10.00
                $table->unsignedSmallInteger('works_count')->default(0);
                $table->unsignedSmallInteger('scored_count')->default(0);
                $table->unsignedSmallInteger('pendings_count')->default(0);
                $table->unsignedSmallInteger('justified_count')->default(0);

                $table->timestamp('computed_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['student_id', 'group_subject_teacher_id', 'week_id'],
                    'u_student_assignment_week'
                );

                // FKs (ajusta a PLURAL si corresponde)
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();

                $table->foreign('group_subject_teacher_id')
                    ->references('id')->on('group_subject_teacher') // o 'group_subject_teachers'
                    ->cascadeOnDelete();

                $table->foreign('week_id')->references('id')->on('weeks')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_weekly_averages');
    }
};
