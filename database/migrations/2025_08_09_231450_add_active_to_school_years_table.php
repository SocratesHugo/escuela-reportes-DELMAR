<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            if (!Schema::hasColumn('school_years', 'active')) {
                $table->boolean('active')->default(false)->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            if (Schema::hasColumn('school_years', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};
