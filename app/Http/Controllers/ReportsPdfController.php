<?php

namespace App\Http\Controllers;

use App\Models\GradeEntry;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Week;
use App\Models\Work;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportsPdfController extends Controller
{
    /**
     * GET /reports/student-week.pdf?student_id=...&week_id=...
     */
    public function studentWeek(Request $request)
    {
        $studentId = (int) $request->query('student_id');
        $weekId    = (int) $request->query('week_id');

        abort_unless($studentId && $weekId, 404, 'Faltan parámetros');

        $student = Student::with('group')->findOrFail($studentId);
        $week    = Week::findOrFail($weekId);

        // assignments del grupo del alumno
        $assignmentIds = GroupSubjectTeacher::where('group_id', $student->group_id)->pluck('id');

        // trabajos de la semana del grupo
        $works = Work::with(['assignment.subject'])
            ->where('week_id', $weekId)
            ->whereIn('group_subject_teacher_id', $assignmentIds)
            ->orderByRaw("FIELD(weekday,'mon','tue','wed','thu','fri'), id")
            ->get();

        // calificaciones del alumno para esos trabajos
        $grades = GradeEntry::where('student_id', $student->id)
            ->whereIn('work_id', $works->pluck('id'))
            ->get()
            ->keyBy('work_id');

        $dayNames = [
            'mon' => 'Lunes', 'tue' => 'Martes', 'wed' => 'Miércoles',
            'thu' => 'Jueves', 'fri' => 'Viernes',
        ];

        // Agrupar trabajos por día para el PDF
        $byDay = collect(['mon','tue','wed','thu','fri'])->mapWithKeys(
            fn ($abbr) => [$abbr => ['abbr' => $abbr, 'label' => $dayNames[$abbr], 'items' => []]]
        )->toArray();

        foreach ($works as $w) {
            $byDay[$w->weekday]['items'][] = $w;
        }

        // Promedios por materia (P=0, J=10, normal=score)
        $buckets = [];
        foreach ($works as $w) {
            $subj = optional($w->assignment?->subject)->name ?? 'Materia';
            $g    = $grades->get($w->id);
            $effective = null;

            if ($g) {
                if ($g->status === 'P')      $effective = 0.0;
                elseif ($g->status === 'J')  $effective = 10.0;
                elseif ($g->score !== null)  $effective = (float) $g->score;
            }

            $buckets[$subj] ??= ['sum' => 0.0, 'count' => 0];
            if (!is_null($effective)) {
                $buckets[$subj]['sum']   += $effective;
                $buckets[$subj]['count'] += 1;
            }
        }

        $subjectAverages = [];
        foreach ($buckets as $subj => $data) {
            $avg = $data['count'] ? round($data['sum'] / $data['count'], 2) : null;
            $subjectAverages[] = ['subject' => $subj, 'average' => $avg];
        }
        usort($subjectAverages, fn ($a, $b) => strcmp($a['subject'], $b['subject']));

        // Render PDF
        $pdf = Pdf::loadView('exports.student-weekly-report-pdf', [
            'student'         => $student,
            'week'            => $week,
            'byDay'           => $byDay,
            'grades'          => $grades,
            'subjectAverages' => $subjectAverages,
        ])->setPaper('a4', 'portrait');

        $filename = 'reporte-semanal-' . $student->id . '-semana-' . $week->id . '.pdf';

        // Descarga directa
        return $pdf->download($filename);
        // Si prefieres abrir en el navegador:
        // return $pdf->stream($filename);
    }
}
