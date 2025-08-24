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
    Schema::table('trimesters', function (Blueprint $table) {
        $table->foreignId('school_year_id')
            ->after('id')
            ->constrained('school_years')
            ->cascadeOnDelete();
    });
}

public function down(): void
{
    Schema::table('trimesters', function (Blueprint $table) {
        $table->dropConstrainedForeignId('school_year_id');
    });
}
};
