<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTitularIdToGroupsTable extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'titular_id')) {
                $table->foreignId('titular_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('school_year_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'titular_id')) {
                $table->dropConstrainedForeignId('titular_id');
                // o, si tu versión no soporta:
                // $table->dropForeign(['titular_id']);
                // $table->dropColumn('titular_id');
            }
        });
    }
}
