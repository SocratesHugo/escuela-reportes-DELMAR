<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si no existiera la tabla, la creamos completa.
        if (! Schema::hasTable('homerooms')) {
            Schema::create('homerooms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->unique('group_id');
            });

            return;
        }

        // La tabla existe: añadimos columnas que falten.
        Schema::table('homerooms', function (Blueprint $table) {
            if (! Schema::hasColumn('homerooms', 'group_id')) {
                $table->foreignId('group_id')
                    ->after('id')
                    ->constrained('groups')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('homerooms', 'teacher_id')) {
                $table->foreignId('teacher_id')
                    ->after('group_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('homerooms', 'active')) {
                $table->boolean('active')->default(true)->after('teacher_id');
            }

            if (! Schema::hasColumn('homerooms', 'created_at') &&
                ! Schema::hasColumn('homerooms', 'updated_at')) {
                $table->timestamps();
            }

            // Único por grupo (si no existe ya)
            try {
                $table->unique('group_id');
            } catch (\Throwable $e) {
                // ignoramos si ya existe
            }
        });
    }

    public function down(): void
    {
        Schema::table('homerooms', function (Blueprint $table) {
            if (Schema::hasColumn('homerooms', 'group_id')) {
                $table->dropForeign(['group_id']);
                $table->dropUnique(['group_id']);
                $table->dropColumn('group_id');
            }
            if (Schema::hasColumn('homerooms', 'teacher_id')) {
                $table->dropForeign(['teacher_id']);
                $table->dropColumn('teacher_id');
            }
            if (Schema::hasColumn('homerooms', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};
