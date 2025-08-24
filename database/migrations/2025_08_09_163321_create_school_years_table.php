<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::dropIfExists('school_years');
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->string('name');    // <- aquí ya va 'name'
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('school_years');
    }
};
