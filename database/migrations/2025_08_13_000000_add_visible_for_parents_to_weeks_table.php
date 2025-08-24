<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agrega la columna solo si no existe (sin "after")
        if (! Schema::hasColumn('weeks', 'visible_for_parents')) {
            Schema::table('weeks', function (Blueprint $table) {
                $table->boolean('visible_for_parents')
                    ->default(false)
                    ->comment('Indica si la semana ya está visible para padres y alumnos');
            });
        }
    }

    public function down(): void
    {
        // La elimina solo si existe
        if (Schema::hasColumn('weeks', 'visible_for_parents')) {
            Schema::table('weeks', function (Blueprint $table) {
                $table->dropColumn('visible_for_parents');
            });
        }
    }
};