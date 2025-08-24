<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asegurar que weeks.starts_at / weeks.ends_at sean DATE NOT NULL
        Schema::table('weeks', function (Blueprint $table) {
            if (Schema::hasColumn('weeks', 'starts_at')) {
                $table->date('starts_at')->nullable(false)->change();
            }
            if (Schema::hasColumn('weeks', 'ends_at')) {
                $table->date('ends_at')->nullable(false)->change();
            }
        });

        // (Opcional, pero recomendado): asegurar que trimesters.starts_at / trimesters.ends_at sean DATE NOT NULL
        Schema::table('trimesters', function (Blueprint $table) {
            if (Schema::hasColumn('trimesters', 'starts_at')) {
                $table->date('starts_at')->nullable(false)->change();
            }
            if (Schema::hasColumn('trimesters', 'ends_at')) {
                $table->date('ends_at')->nullable(false)->change();
            }
        });
    }

    public function down(): void
    {
        // Si antes eran DATETIME y quieres volver, cambia aquí a ->dateTime()
        Schema::table('weeks', function (Blueprint $table) {
            if (Schema::hasColumn('weeks', 'starts_at')) {
                $table->date('starts_at')->nullable(false)->change();
            }
            if (Schema::hasColumn('weeks', 'ends_at')) {
                $table->date('ends_at')->nullable(false)->change();
            }
        });

        Schema::table('trimesters', function (Blueprint $table) {
            if (Schema::hasColumn('trimesters', 'starts_at')) {
                $table->date('starts_at')->nullable(false)->change();
            }
            if (Schema::hasColumn('trimesters', 'ends_at')) {
                $table->date('ends_at')->nullable(false)->change();
            }
        });
    }
};
