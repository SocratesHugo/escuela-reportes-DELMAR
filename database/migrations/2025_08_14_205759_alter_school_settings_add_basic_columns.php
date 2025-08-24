<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('school_settings')) {
            // Si por alguna razón la tabla no existe, créala completa
            Schema::create('school_settings', function (Blueprint $table) {
                $table->id();
                $table->string('school_name')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('primary_color', 20)->nullable();
                $table->string('secondary_color', 20)->nullable();
                $table->string('contact_email')->nullable();
                $table->timestamps();
            });
            return;
        }

        Schema::table('school_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('school_settings', 'school_name')) {
                $table->string('school_name')->nullable();
            }
            if (! Schema::hasColumn('school_settings', 'logo_path')) {
                $table->string('logo_path')->nullable();
            }
            if (! Schema::hasColumn('school_settings', 'primary_color')) {
                $table->string('primary_color', 20)->nullable();
            }
            if (! Schema::hasColumn('school_settings', 'secondary_color')) {
                $table->string('secondary_color', 20)->nullable();
            }
            if (! Schema::hasColumn('school_settings', 'contact_email')) {
                $table->string('contact_email')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No borramos columnas en down para no perder datos accidentalmente
        // Si lo necesitas, aquí podrías hacer dropColumn(...).
    }
};
