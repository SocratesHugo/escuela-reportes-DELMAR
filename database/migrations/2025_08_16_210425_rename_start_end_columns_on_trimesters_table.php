<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // up: start_date -> starts_at ; end_date -> ends_at
    public function up(): void
    {
        Schema::table('trimesters', function (Blueprint $table) {
            // Nos aseguramos de que existan antes de renombrar (por seguridad en distintos entornos)
            if (Schema::hasColumn('trimesters', 'start_date')) {
                $table->renameColumn('start_date', 'starts_at');
            }
            if (Schema::hasColumn('trimesters', 'end_date')) {
                $table->renameColumn('end_date', 'ends_at');
            }
        });
    }

    // down: revertir a los nombres originales
    public function down(): void
    {
        Schema::table('trimesters', function (Blueprint $table) {
            if (Schema::hasColumn('trimesters', 'starts_at')) {
                $table->renameColumn('starts_at', 'start_date');
            }
            if (Schema::hasColumn('trimesters', 'ends_at')) {
                $table->renameColumn('ends_at', 'end_date');
            }
        });
    }
};
