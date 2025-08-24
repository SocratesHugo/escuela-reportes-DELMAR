<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('preceptor_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preceptor_id')->constrained('users')->cascadeOnDelete(); // user con rol preceptor
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['preceptor_id','student_id']); // no duplicar
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preceptor_student');
    }
};
