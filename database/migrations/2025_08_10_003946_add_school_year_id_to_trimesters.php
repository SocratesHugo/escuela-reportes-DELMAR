<?php

// database/migrations/xxxx_add_school_year_id_to_trimesters.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('trimesters', function (Blueprint $t) {
            if (!Schema::hasColumn('trimesters','school_year_id')) {
                $t->foreignId('school_year_id')->after('id')->constrained()->cascadeOnDelete();
            }
        });
    }
    public function down(): void {
        Schema::table('trimesters', function (Blueprint $t) {
            if (Schema::hasColumn('trimesters','school_year_id')) {
                $t->dropConstrainedForeignId('school_year_id');
            }
        });
    }
};


