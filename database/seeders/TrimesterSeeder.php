<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrimesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schoolYearId = 1; // Ajusta según tu ciclo escolar activo

        DB::table('trimesters')->insert([
            [
                'school_year_id' => $schoolYearId,
                'name' => '1er Trimestre',
                'start_date' => Carbon::create(2025, 9, 1),
                'end_date'   => Carbon::create(2025, 11, 30),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_year_id' => $schoolYearId,
                'name' => '2do Trimestre',
                'start_date' => Carbon::create(2025, 12, 1),
                'end_date'   => Carbon::create(2026, 3, 1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_year_id' => $schoolYearId,
                'name' => '3er Trimestre',
                'start_date' => Carbon::create(2026, 3, 2),
                'end_date'   => Carbon::create(2026, 6, 15),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
