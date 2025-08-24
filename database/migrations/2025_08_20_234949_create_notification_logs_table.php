<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('notification_rule_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->json('snapshot')->nullable(); // guardamos conteos/materias
            $t->timestamp('sent_at');
            $t->timestamps();

            $t->index(['notification_rule_id','student_id','sent_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('notification_logs');
    }
};
