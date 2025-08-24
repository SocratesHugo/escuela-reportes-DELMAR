<?php

namespace App\Exports\Sheets;

use App\Models\GradeEntry;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Week;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class SummarySheet implements FromArray, WithTitle
{
    public function __construct(
        public ?int $gstId = null,
        public ?Carbon $dateFrom = null,
        public ?Carbon $dateTo = null,
    ){}

    public function title(): string
    {
        return 'Resumen';
    }

    public function array(): array
    {
        // Trabajos del rango (si se especifica) y opcionalmente de un GST concreto
        $worksQ = Work::query()->with(['assignment','assignment.subject','assignment.group','week']);

        if ($this->gstId) {
            $worksQ->where('group_subject_teacher_id', $this->gstId);
        }
        if ($this->dateFrom) {
            $worksQ->whereHas('week', fn($q) => $q->whereDate('starts_at', '>=', $this->dateFrom->toDateString()));
        }
        if ($this->dateTo) {
            $worksQ->whereHas('week', fn($q) => $q->whereDate('ends_at', '<=', $this->dateTo->toDateString()));
        }

        $works = $worksQ->get();
        if ($works->isEmpty()) {
            return [['Grupo','Alumno','Materia','T1 Promedio','T2 Promedio','T3 Promedio']];
        }

        // Alumnos del/los grupos involucrados
        $groupIds = $works->pluck('group_subject_teacher.group_id')->filter()->unique()->values();
        $students = Student::query()->whereIn('group_id', $groupIds)->with('group')->get();

        // Armado: alumno × materia × term => avg
        $rows = [];
        foreach ($students as $student) {
            // por materias que aparecen en works de sus grupos
            $studentWorks = $works->filter(fn($w) => $w->assignment?->group_id === $student->group_id);

            $subjects = $studentWorks->pluck('assignment.subject.name')->filter()->unique()->values();
            foreach ($subjects as $subjectName) {
                $acc = [1 => ['sum'=>0,'n'=>0], 2 => ['sum'=>0,'n'=>0], 3 => ['sum'=>0,'n'=>0]];

                $subWorks = $studentWorks->filter(fn($w) => ($w->assignment?->subject?->name ?? null) === $subjectName);
                if ($subWorks->isEmpty()) continue;

                $grades = GradeEntry::where('student_id', $student->id)
                    ->whereIn('work_id', $subWorks->pluck('id'))
                    ->get()->keyBy('work_id');

                foreach ($subWorks as $w) {
                    $term = $this->termOfWeek($w->week);
                    $g = $grades->get($w->id);
                    if (!$g) continue;

                    $eff = $this->effective($g->status, $g->score);
                    if (!is_null($eff)) {
                        $acc[$term]['sum'] += $eff;
                        $acc[$term]['n']   += 1;
                    }
                }

                $t1 = $acc[1]['n'] ? round($acc[1]['sum']/$acc[1]['n'], 2) : null;
                $t2 = $acc[2]['n'] ? round($acc[2]['sum']/$acc[2]['n'], 2) : null;
                $t3 = $acc[3]['n'] ? round($acc[3]['sum']/$acc[3]['n'], 2) : null;

                $rows[] = [
                    $student->group?->name ?? '—',
                    trim("{$student->paternal_lastname} {$student->maternal_lastname}, {$student->names}"),
                    $subjectName,
                    is_null($t1) ? '—' : $t1,
                    is_null($t2) ? '—' : $t2,
                    is_null($t3) ? '—' : $t3,
                ];
            }
        }

        array_unshift($rows, ['Grupo','Alumno','Materia','T1 Promedio','T2 Promedio','T3 Promedio']);
        return $rows;
    }

    protected function effective(?string $status, ?float $score): ?float
    {
        if ($status === 'P') return 0.0;
        if ($status === 'J') return 10.0;
        if ($score === null) return null;
        return (float)$score;
    }

    protected function termOfWeek(?Week $week): int
    {
        // Si tienes columna term en weeks, úsala:
        if ($week && isset($week->term) && in_array((int)$week->term, [1,2,3])) {
            return (int)$week->term;
        }
        // Fallback: por mes (ajústalo a tu calendario escolar)
        $m = $week?->starts_at ? Carbon::parse($week->starts_at)->month : 1;
        return $m <= 11 ? 1 : 1; // ⚠️ Deja 1 por defecto si no tienes términos; ajusta a tu regla real.
    }
}
