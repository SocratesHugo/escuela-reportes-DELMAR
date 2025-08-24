<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weekly_report_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('week_id');
            $table->string('parent_name')->nullable();
            $table->string('parent_email')->nullable();
            $table->timestamp('signed_at')->nullable();

            // Meta opcional útil
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('week_id')->references('id')->on('weeks')->cascadeOnDelete();

            // 1 registro por alumno/semana
            $table->unique(['student_id', 'week_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_report_signatures');
    }
};
