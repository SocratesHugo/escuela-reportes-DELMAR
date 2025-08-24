<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('auto_close_rules')) {
            Schema::create('auto_close_rules', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('group_subject_teacher_id')->nullable(); // null = aplica a todas
                $table->unsignedTinyInteger('weekday'); // 1..7
                $table->time('run_time');
                $table->string('timezone', 64)->default('America/Mazatlan');
                $table->boolean('is_enabled')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->string('close_cutoff', 32)->default('yesterday');
                $table->timestamps();

                $table->foreign('group_subject_teacher_id')
                    ->references('id')->on('group_subject_teacher') // o 'group_subject_teachers'
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_close_rules');
    }
};
