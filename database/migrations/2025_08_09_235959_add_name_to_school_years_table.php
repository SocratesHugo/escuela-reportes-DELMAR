<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('school_years', function (Blueprint $table) {
            if (!Schema::hasColumn('school_years', 'name')) {
                $table->string('name')->after('id');
            }
        });
    }
    public function down(): void {
        Schema::table('school_years', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
