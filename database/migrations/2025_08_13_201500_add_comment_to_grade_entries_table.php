<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('grade_entries', 'comment')) {
            Schema::table('grade_entries', function (Blueprint $table) {
                $table->text('comment')->nullable()->after('score');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('grade_entries', 'comment')) {
            Schema::table('grade_entries', function (Blueprint $table) {
                $table->dropColumn('comment');
            });
        }
    }
};
