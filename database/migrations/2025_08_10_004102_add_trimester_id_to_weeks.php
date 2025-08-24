<?php

// database/migrations/xxxx_add_trimester_id_to_weeks.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('weeks', function (Blueprint $t) {
            if (!Schema::hasColumn('weeks','trimester_id')) {
                $t->foreignId('trimester_id')->after('id')->constrained()->cascadeOnDelete();
            }
            // Opcional: fechas para tu UI de intervalo
            if (!Schema::hasColumn('weeks','starts_at')) $t->date('starts_at')->nullable()->after('name');
            if (!Schema::hasColumn('weeks','ends_at'))   $t->date('ends_at')->nullable()->after('starts_at');
        });
    }
    public function down(): void {
        Schema::table('weeks', function (Blueprint $t) {
            if (Schema::hasColumn('weeks','ends_at')) $t->dropColumn('ends_at');
            if (Schema::hasColumn('weeks','starts_at')) $t->dropColumn('starts_at');
            if (Schema::hasColumn('weeks','trimester_id')) $t->dropConstrainedForeignId('trimester_id');
        });
    }
};
