<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\SchoolSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Reporte semanal “como lo ven papás/alumnos”.
     * URL: /reporte-semanal/{student}
     */
    public function weekly(Student $student)
    {
        // Autorización básica:
        if (! $this->canViewReport($student)) {
            abort(403, 'No tienes permisos para ver este reporte.');
        }

        // Arma los datos del reporte:
        $data = $this->buildWeeklyData($student);

        // Renderiza la vista pública (no-Filament)
        return view('reports.weekly', compact('student'));
    }

    /**
     * ¿Puede el usuario autenticado ver el reporte del alumno?
     * - Admin / preceptor / titular
     * - El propio alumno (email coincide)
     * - Cualquier padre/tutor vinculado al alumno
     */
    private function canViewReport(Student $student): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'preceptor', 'titular'])) {
            return true;
        }

        if (! empty($student->email) && $user->email === $student->email) {
            return true;
        }

        if (method_exists($student, 'parents') && $student->parents()->whereKey($user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Construye los datos del reporte.
     * Ajusta aquí para traer datos reales desde tus repositorios/consultas.
     */
    private function buildWeeklyData(Student $student): array
    {
        $now   = Carbon::now();
        $start = $now->copy()->startOfWeek(Carbon::MONDAY);
        $end   = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $week = [
            'name'  => 'Semana ' . $now->weekOfYear,
            'range' => $start->translatedFormat('d M') . ' — ' . $end->translatedFormat('d M Y'),
        ];

        $settings = SchoolSetting::query()->first();

        $logoUrl = null;
        if ($settings && $settings->logo_url) {
            $logoUrl = str_starts_with($settings->logo_url, 'http')
                ? $settings->logo_url
                : asset('storage/' . ltrim($settings->logo_url, '/'));
        }

        $branding = [
            'school_name'    => $settings->name ?? 'Colegio',
            'logo_url'       => $logoUrl,
            'primary_color'  => $settings->primary_color  ?? '#2563eb',
            'secondary_color'=> $settings->secondary_color?? '#7c3aed',
            'text_color'     => $settings->text_color     ?? '#111827',
        ];

        // TODO: Reemplaza los dummy con tus datos reales
        $summary = [
            'avg'       => 8.6,
            'completed' => 12,
            'pending'   => 2,
        ];

        $grades = collect([
            [
                'subject' => 'Matemáticas',
                'work'    => 'Tarea 3',
                'date'    => $now->copy()->subDays(3)->toDateString(),
                'score'   => 9.5,
                'max'     => 10,
            ],
            [
                'subject' => 'Historia',
                'work'    => 'Exposición',
                'date'    => $now->copy()->subDays(4)->toDateString(),
                'score'   => 7.8,
                'max'     => 10,
            ],
        ]);

        $attendance = [
            'present'   => 23,
            'absent'    => 1,
            'late'      => 0,
            'justified' => 1,
        ];

        $teacherNotes = [
            'Mantuvo buena participación en clase.',
            'Reforzar lectura comprensiva en casa.',
        ];

        return compact('week', 'branding', 'summary', 'grades', 'attendance', 'teacherNotes');
    }
}
