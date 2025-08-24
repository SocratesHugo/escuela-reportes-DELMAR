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

class TermMatrixSheet implements FromArray, WithTitle
{
    public function __construct(
        public int $matrixGstId,
        public int $matrixGroupId,
        public int $term,
    ) {}

    public function title(): string
    {
        return 'Matriz Trimestre';
    }

    public function array(): array
    {
        $gst = GroupSubjectTeacher::with(['group','subject'])->find($this->matrixGstId);
        if (!$gst) {
            return [['Alumno','Materia','(sin datos)']];
        }

        $subjectName = $gst->subject?->name ?? 'Materia';
        $groupId     = $this->matrixGroupId;

        // Weeks del trimestre
        $weekIds = Week::query()
            ->when(true, function ($q) {
                // Si tu tabla weeks tiene columna term:
                $q->where('term', $this->term);
            })
            ->pluck('id');

        // Trabajos de la materia/grupo en ese trimestre
        $works = Work::query()
            ->with(['week'])
            ->where('group_subject_teacher_id', $gst->id)
            ->whereIn('week_id', $weekIds)
            ->orderBy('week_id')
            ->orderByRaw("FIELD(weekday,'mon','tue','wed','thu','fri'), id")
            ->get();

        // Cabecera dinámica: Trabajo 1..N
        $head = ['Alumno','Materia'];
        foreach ($works as $idx => $w) {
            $wk = $w->week?->name ?? '';
            $dates = $this->weekRange($w->week);
            $head[] = "Trabajo ".($idx+1)." ({$wk})";
        }
        $head[] = 'Promedio';

        // Alumnos del grupo
        $students = Student::query()->where('group_id', $groupId)->orderBy('paternal_lastname')->orderBy('maternal_lastname')->orderBy('names')->get();

        $rows = [$head];

        foreach ($students as $st) {
            $grades = GradeEntry::where('student_id', $st->id)
                ->whereIn('work_id', $works->pluck('id'))
                ->get()->keyBy('work_id');

            $line = [
                trim("{$st->paternal_lastname} {$st->maternal_lastname}, {$st->names}"),
                $subjectName,
            ];

            $sum = 0.0; $count = 0;

            foreach ($works as $w) {
                $g = $grades->get($w->id);
                $eff = $this->effective($g->status ?? null, $g->score ?? null);
                $line[] = is_null($eff) ? '—' : number_format($eff, 2);
                if (!is_null($eff)) { $sum += $eff; $count++; }
            }

            $avg = $count ? round($sum / $count, 2) : null;
            $line[] = is_null($avg) ? '—' : $avg;

            $rows[] = $line;
        }

        return $rows;
    }

    protected function weekRange(?Week $week): string
    {
        if (!$week) return '';
        $s = $week->starts_at ? Carbon::parse($week->starts_at)->format('Y-m-d') : null;
        $e = $week->ends_at   ? Carbon::parse($week->ends_at)->format('Y-m-d')   : null;
        return ($s && $e) ? "{$s} a {$e}" : '';
    }

    protected function effective(?string $status, ?float $score): ?float
    {
        if ($status === 'P') return 0.0;
        if ($status === 'J') return 10.0;
        if ($score === null) return null;
        return (float)$score;
    }
}
