<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('assignment_week_states')) {
            Schema::create('assignment_week_states', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_subject_teacher_id');
                $table->unsignedBigInteger('week_id');
                $table->boolean('is_closed')->default(false);
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique(['group_subject_teacher_id', 'week_id'], 'u_assignment_week');

                $table->foreign('group_subject_teacher_id')
                    ->references('id')->on('group_subject_teacher') // o 'group_subject_teachers'
                    ->cascadeOnDelete();

                $table->foreign('week_id')->references('id')->on('weeks')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_week_states');
    }
};
