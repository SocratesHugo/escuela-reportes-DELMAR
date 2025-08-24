<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();             // weekly_report, nudge_late, etc.
            $table->string('name');                      // nombre visible
            $table->string('subject_template');          // asunto con variables
            $table->text('body_template');               // cuerpo con variables (HTML o texto)
            $table->boolean('is_html')->default(true);   // si el cuerpo es HTML
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('email_templates');
    }
};
