<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Exports\QuickCsvController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\ReportsPdfController;
use App\Http\Controllers\StudentImportController;

use App\Models\Student;
use App\Models\Week;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect('/admin'));

require __DIR__ . '/auth.php';

/**
 * Rutas públicas firmadas (sin login)
 * - Ver reporte público
 * - Firmar reporte público
 * - Puente a la página semanal de Filament (firmada)
 *
 * Importante: los nombres de ruta deben ser únicos para permitir route:cache
 */
Route::middleware(['signed', 'throttle:60,1'])->group(function () {
    // Ver reporte público
    Route::get('/r/{parent}/{student}/{week}', [PublicReportController::class, 'show'])
        ->whereNumber('parent')
        ->whereNumber('student')
        ->whereNumber('week')
        ->name('public.reports.show'); // <-- único

    // Firmar el reporte público
    Route::post('/r/{parent}/{student}/{week}/sign', [PublicReportController::class, 'sign'])
        ->whereNumber('parent')
        ->whereNumber('student')
        ->whereNumber('week')
        ->name('public.reports.sign'); // <-- único

    // Redirección firmada a la página semanal de Filament
    Route::get('/r/weekly/{student}/{week}', function (Student $student, Week $week) {
        $filamentUrl = route('filament.admin.pages.student-weekly-report', [
            'public'     => 1,
            'parent'     => 1,
            'student_id' => $student->id,
            'week_id'    => $week->id,
        ]);
        return redirect()->to($filamentUrl);
    })->name('public.weekly'); // <-- único
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
        ->whereNumber('weekId')
        ->name('export.weekly-group-summary');

    Route::get('/exports/group-snapshot/{groupId}/{weekId}', [QuickCsvController::class, 'groupSnapshot'])
        ->whereNumber('groupId')
        ->whereNumber('weekId')
        ->name('export.group-snapshot');

    Route::post('/students/import', [StudentImportController::class, 'import'])
        ->name('students.import')
        ->middleware('permission:students.import');
});

/**
 * Login de Filament (POST) — evita 405 cuando Filament no registra su ruta
 * No usa el LoginController de Filament (para no fallar en route:list).
 */
Route::post('/admin/login', function (Request $request) {
    $credentials = $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (Auth::attempt($credentials, $request->boolean('remember'))) {
        $request->session()->regenerate();
        return redirect()->intended('/admin');
    }

    return back()
        ->withErrors(['email' => 'Credenciales inválidas.'])
        ->onlyInput('email');
})->middleware('guest')->name('filament.admin.login');

