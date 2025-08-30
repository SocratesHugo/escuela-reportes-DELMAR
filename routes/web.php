<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

use App\Http\Controllers\Exports\QuickCsvController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\PublicWeeklyReportController;
use App\Http\Controllers\ReportsPdfController;
use App\Http\Controllers\StudentImportController;

use Filament\Http\Controllers\Auth\LoginController;


use App\Models\Student;
use App\Models\Week;

Route::get('/', fn () => redirect('/admin'));

require __DIR__ . '/auth.php';

/**
 * Rutas públicas firmadas (sin login)
 * - Ver reporte público
 * - Firmar reporte público
 * - Puente a la página semanal de Filament (firmada)
 */
Route::middleware(['signed', 'throttle:60,1'])->group(function () {
    // Ver reporte público
    Route::get('/r/{parent}/{student}/{week}', [PublicReportController::class, 'show'])
        ->whereNumber('parent')->whereNumber('student')->whereNumber('week')
        ->name('public.reports.show');

    // Firmar reporte público
    Route::post('/r/{parent}/{student}/{week}/sign', [PublicReportController::class, 'sign'])
        ->whereNumber('parent')->whereNumber('student')->whereNumber('week')
        ->name('public.reports.sign');

    // Redirección firmada a la página semanal de Filament
    Route::get('/r/weekly/{student}/{week}', function (Student $student, Week $week) {
        $filamentUrl = route('filament.admin.pages.student-weekly-report', [
            'public'     => 1,
            'parent'     => 1,
            'student_id' => $student->id,
            'week_id'    => $week->id,
        ]);
        return redirect()->to($filamentUrl);
    })->name('public.weekly');
});

/**
 * Zona Admin (con login)
 */
Route::middleware(['auth'])->prefix('admin')->as('admin.')->group(function () {
    if (class_exists(ReportsPdfController::class)) {
        Route::get('/reports/student-week-pdf', [ReportsPdfController::class, 'studentWeekPdf'])
            ->name('reports.student.week.pdf');
    }

    Route::get('/exports/weekly-group-summary/{weekId}', [QuickCsvController::class, 'weeklyGroupSummary'])
        ->name('export.weekly-group-summary');

    Route::get('/exports/group-snapshot/{groupId}/{weekId}', [QuickCsvController::class, 'groupSnapshot'])
        ->name('export.group-snapshot');

    Route::post('/students/import', [StudentImportController::class, 'import'])
        ->name('students.import')
        ->middleware('permission:students.import');
});
// Parche: manejar el POST del login de Filament cuando no se registra por sí solo
Route::post('/admin/login', [LoginController::class, 'store'])
    ->name('filament.admin.login');
