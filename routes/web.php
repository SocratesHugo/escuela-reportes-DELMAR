<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\ReportsPdfController; // si lo usas
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\PublicReportSigningController;
use App\Http\Controllers\PublicWeeklyReportController;

use Illuminate\Support\Facades\URL;
use App\Models\Student;
use App\Models\Week;

use App\Http\Controllers\Exports\QuickCsvController;



Route::get('/', function () {
    return view('welcome');
});

require __DIR__ . '/auth.php';

// Bloque firmado (sin login) para ver/firmar reportes
Route::middleware(['signed','throttle:60,1'])->group(function () {
    Route::get('/r/{parent}/{student}/{week}', [PublicReportController::class, 'show'])
        ->name('public.report.show');

    Route::post('/r/{parent}/{student}/{week}/sign', [PublicReportController::class, 'sign'])
        ->name('public.report.sign');
});

// (Opcional) si tu PDF está detrás de admin
Route::middleware(['auth'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        if (class_exists(ReportsPdfController::class)) {
            Route::get('/reports/student-week-pdf', [ReportsPdfController::class, 'studentWeekPdf'])
                ->name('reports.student.week.pdf');
        }
    });

    Route::middleware(['auth'])->group(function () {
    Route::post('/students/import', [StudentImportController::class, 'import'])
        ->name('students.import')
        ->middleware('permission:students.import');
});

Route::post('/public/reports/sign', [PublicReportSigningController::class, 'store'])
    ->name('public.reports.sign');

    /*
|--------------------------------------------------------------------------
| Rutas públicas firmadas para reportes semanales
|--------------------------------------------------------------------------
|
| Estas rutas NO muestran la UI de Filament; sólo validan la firma y
| redirigen a la página de Filament con los parámetros adecuados.
| Si alguien altera los parámetros, la firma deja de ser válida y
| se devuelve 403 automáticamente gracias al middleware 'signed'.
|
*/

Route::get('/r/weekly/{student}/{week}', function (Student $student, Week $week) {
    // Redirige a la página de Filament con los flags públicos
    $filamentUrl = route('filament.admin.pages.student-weekly-report', [
        'public'     => 1,
        'parent'     => 1,            // si lo usas para modo "tutor/padre"
        'student_id' => $student->id,
        'week_id'    => $week->id,
    ]);

    return redirect()->to($filamentUrl);
})->name('public.weekly')->middleware('signed');


Route::middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/exports/weekly-group-summary/{weekId}', [QuickCsvController::class, 'weeklyGroupSummary'])
        ->name('export.weekly-group-summary');

    Route::get('/exports/group-snapshot/{groupId}/{weekId}', [QuickCsvController::class, 'groupSnapshot'])
        ->name('export.group-snapshot');
});


Route::get('/r/{student}/{week}', [PublicReportsController::class, 'show'])
    ->name('public.report.show')
    ->middleware('signed');


    // --- Reporte público y firma (enlaces firmados) ---
Route::middleware('signed')->group(function () {
    // Ver el reporte semanal público
    Route::get('/r/{week}/{student}/{parent}', [PublicWeeklyReportController::class, 'show'])
        ->name('public.report.show');

    // Registrar la firma del padre/madre
    Route::post('/r/{week}/{student}/{parent}/sign', [PublicWeeklyReportController::class, 'sign'])
        ->name('public.report.sign');

        });

        Route::post('/report/sign/{parent}/{student}/{week}', [PublicReportController::class, 'sign'])
    ->name('public.report.sign');
