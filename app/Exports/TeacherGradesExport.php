<?php

namespace App\Exports;

use App\Models\GradeEntry;
use App\Models\Group;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Week;
use App\Models\Work;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class TeacherGradesExport implements WithMultipleSheets
{
    public function __construct(
        public ?int $gstId = null,           // Filtro Materia–Grupo (opcional) para Resumen/Detalle
        public ?int $weekFromId = null,      // Semana desde (opcional)
        public ?int $weekToId = null,        // Semana hasta (opcional)

        // Parámetros para la Matriz por trabajo (opcional)
        public ?int $matrixGstId = null,     // Materia–Grupo específico
        public ?int $matrixGroupId = null,   // Grupo (útil por UI)
        public ?int $matrixTerm = null       // Trimestre (1/2/3) asociado a weeks.trimester_id
    ) {}

    public function sheets(): array
    {
        [$works, $grades] = $this->fetchData();

        $sheets = [
            new ResumenSheet($works, $grades, $this->gstId, $this->weekFromId, $this->weekToId),
            new DetalleSheet($works, $grades),
        ];

        // Hoja adicional si se proporcionan los 3 parámetros de matriz
        if ($this->matrixGstId && $this->matrixGroupId && $this->matrixTerm) {
            $sheets[] = new MatrixByWorkSheet(
                matrixGstId:   $this->matrixGstId,
                matrixGroupId: $this->matrixGroupId,
                matrixTerm:    $this->matrixTerm
            );
        }

        return $sheets;
    }

    /**
     * Obtiene Works (trabajos) y sus GradeEntry respetando filtros.
     */
    protected function fetchData(): array
    {
        $worksQ = Work::query()
            ->with([
                'assignment.subject:id,name',
                'week:id,name,starts_at,ends_at,trimester_id',
                'groupSubjectTeacher.group:id,name',
                'groupSubjectTeacher.subject:id,name',
            ]);

        if ($this->gstId) {
            $worksQ->where('group_subject_teacher_id', $this->gstId);
        }

        if ($this->weekFromId) {
            $worksQ->where('week_id', '>=', $this->weekFromId);
        }
        if ($this->weekToId) {
            $worksQ->where('week_id', '<=', $this->weekToId);
        }

        $works = $worksQ->orderBy('week_id')->orderBy('id')->get();

        if ($works->isEmpty()) {
            return [collect(), collect()];
        }

        $grades = GradeEntry::query()
            ->with([
                'student:id,group_id,names,paternal_lastname,maternal_lastname',
                'work:id,name,week_id,group_subject_teacher_id',
                'work.week:id,name,starts_at,ends_at,trimester_id',
                'work.groupSubjectTeacher.group:id,name',
                'work.groupSubjectTeacher.subject:id,name',
            ])
            ->whereIn('work_id', $works->pluck('id'))
            ->orderBy('work_id')
            ->get();

        return [$works, $grades];
    }
}

/* ============================================================
 |  Hoja: Resumen
 *============================================================ */
class ResumenSheet implements FromArray, WithTitle
{
    public function __construct(
        protected Collection $works,
        protected Collection $grades,
        protected ?int $gstId,
        protected ?int $weekFromId,
        protected ?int $weekToId,
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Grupo', 'Materia', 'T1', 'T2', 'T3', 'Promedio'];

        $byGst = $this->works->groupBy('group_subject_teacher_id');

        foreach ($byGst as $gstWorks) {
            /** @var GroupSubjectTeacher|null $gst */
            $gst = $gstWorks->first()?->groupSubjectTeacher;
            $groupName   = $gst?->group?->name ?? '—';
            $subjectName = $gst?->subject?->name ?? '—';

            $avg = [1 => null, 2 => null, 3 => null];
            $sum = [1 => 0.0,  2 => 0.0,  3 => 0.0];
            $cnt = [1 => 0,    2 => 0,    3 => 0];

            foreach ($gstWorks as $w) {
                $termId  = (int) ($w->week?->trimester_id ?? 0);
                foreach ($this->grades->where('work_id', $w->id) as $g) {
                    $eff = $this->effectiveScore($g->status, $g->score);
                    if ($eff === null) continue;
                    $sum[$termId] += $eff;
                    $cnt[$termId] += 1;
                }
            }

            foreach ([1,2,3] as $t) {
                $avg[$t] = $cnt[$t] ? round($sum[$t] / $cnt[$t], 2) : null;
            }

            $allSum = array_sum($sum);
            $allCnt = array_sum($cnt);
            $overall = $allCnt ? round($allSum / $allCnt, 2) : null;

            $rows[] = [
                $groupName,
                $subjectName,
                $avg[1] ?? '—',
                $avg[2] ?? '—',
                $avg[3] ?? '—',
                $overall ?? '—',
            ];
        }

        return $rows;
    }

    /**
     * Reglas de calificación efectiva:
     * J = 10, P = 0, numérico = score, null = no cuenta.
     */
    protected function effectiveScore(?string $status, $score): ?float
    {
        if ($status === 'J') return 10.0;
        if ($status === 'P') return 0.0;      // <- ahora P cuenta como 0
        if ($score === null) return null;
        return (float) $score;
    }
}

/* ============================================================
 |  Hoja: Detalle (Alumno = nombre completo)
 *============================================================ */
class DetalleSheet implements FromArray, WithTitle
{
    public function __construct(
        protected Collection $works,
        protected Collection $grades
    ) {}

    public function title(): string
    {
        return 'Detalle';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Grupo', 'Materia', 'Alumno', 'Trabajo', 'Semana', 'Estatus', 'Calificación (efectiva)'];

        $gradesByWork = $this->grades->groupBy('work_id');

        foreach ($this->works as $w) {
            $gGroup   = $w->groupSubjectTeacher?->group?->name ?? '—';
            $gSubject = $w->groupSubjectTeacher?->subject?->name ?? '—';
            $weekLbl  = $this->weekLabel($w->week);

            foreach ($gradesByWork->get($w->id, collect()) as $g) {
                $studentName = $this->studentFullName($g->student);
                $effective   = $this->effectiveScore($g->status, $g->score);

                $rows[] = [
                    $gGroup,
                    $gSubject,
                    $studentName,
                    $w->name ?? 'Trabajo',
                    $weekLbl,
                    $g->status ?? 'normal',
                    $effective,
                ];
            }
        }

        return $rows;
    }

    protected function studentFullName($student): string
    {
        if (!$student) return '—';
        $last = trim(($student->paternal_lastname ?? '') . ' ' . ($student->maternal_lastname ?? ''));
        $name = trim($student->names ?? '');
        return trim($last . ' ' . $name) ?: ($student->name ?? '—');
    }

    protected function weekLabel(?Week $week): string
    {
        if (!$week) return '—';
        $start = $week->starts_at ? \Illuminate\Support\Carbon::parse($week->starts_at)->format('Y-m-d') : '';
        $end   = $week->ends_at   ? \Illuminate\Support\Carbon::parse($week->ends_at)->format('Y-m-d')   : '';
        $name  = $week->name ?? 'Semana';
        return trim("$name — $start - $end");
    }

    /**
     * Reglas de calificación efectiva:
     * J = 10, P = 0, numérico = score, null = no cuenta.
     */
    protected function effectiveScore(?string $status, $score): ?float
    {
        if ($status === 'J') return 10.0;
        if ($status === 'P') return 0.0;      // <- ahora P cuenta como 0
        if ($score === null) return null;
        return (float) $score;
    }
}

/* ============================================================
 |  Hoja: Matriz por trabajo (por trimestre)
 |  Muestra encabezados con el NOMBRE DEL TRABAJO.
 *============================================================ */
class MatrixByWorkSheet implements FromArray, WithTitle
{
    public function __construct(
        protected int $matrixGstId,
        protected int $matrixGroupId,
        protected int $matrixTerm
    ) {}

    public function title(): string
    {
        return 'Matriz por trabajo';
    }

    public function array(): array
    {
        $rows = [];

        // 1) Trabajos del GST en el trimestre solicitado (ordenados)
        $works = Work::query()
            ->with(['week:id,name,starts_at,ends_at,trimester_id', 'groupSubjectTeacher.subject:id,name'])
            ->where('group_subject_teacher_id', $this->matrixGstId)
            ->whereHas('week', fn ($q) => $q->where('trimester_id', $this->matrixTerm))
            ->orderBy('week_id')
            ->orderBy('id')
            ->get();

        // 2) Alumnos del grupo (todos)
        $students = Student::query()
            ->where('group_id', $this->matrixGroupId)
            ->orderBy('paternal_lastname')
            ->orderBy('maternal_lastname')
            ->orderBy('names')
            ->get(['id','names','paternal_lastname','maternal_lastname']);

        // 3) Notas para esos works y alumnos
        $grades = GradeEntry::query()
            ->with(['student:id,names,paternal_lastname,maternal_lastname'])
            ->whereIn('work_id', $works->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get();

        // 4) Encabezados dinámicos con el NOMBRE DEL TRABAJO
        $subjectName = optional($works->first()?->groupSubjectTeacher?->subject)->name ?? 'Materia';

        $header = ['Alumno', 'Materia'];
        foreach ($works as $index => $w) {
            $workName = trim($w->name ?? ('Trabajo ' . ($index + 1)));
            $header[] = 'Trabajo ' . ($index + 1) . ': ' . $workName;
        }
        $header[] = 'Promedio';
        $rows[] = $header;

        if ($works->isEmpty() || $students->isEmpty()) {
            $rows[] = ['Sin datos para la selección (GST/Grupo/Trimestre).'];
            return $rows;
        }

        // 5) Indizar calificaciones por [student_id][work_id]
        $byStudentWork = [];
        foreach ($grades as $g) {
            $byStudentWork[$g->student_id][$g->work_id] = $this->effectiveScore($g->status, $g->score);
        }

        // 6) Filas por alumno
        foreach ($students as $s) {
            $row = [];
            $row[] = $this->studentFullName($s);
            $row[] = $subjectName;

            $sum = 0.0; $cnt = 0;

            foreach ($works as $w) {
                $eff = $byStudentWork[$s->id][$w->id] ?? null;
                $row[] = $eff;
                if (is_numeric($eff)) {
                    $sum += (float) $eff;
                    $cnt += 1;
                }
            }

            $row[] = $cnt ? round($sum / $cnt, 2) : null;
            $rows[] = $row;
        }

        return $rows;
    }

    protected function studentFullName($student): string
    {
        if (!$student) return '—';
        $last = trim(($student->paternal_lastname ?? '') . ' ' . ($student->maternal_lastname ?? ''));
        $name = trim($student->names ?? '');
        return trim($last . ' ' . $name) ?: ($student->name ?? '—');
    }

    /**
     * Reglas de calificación efectiva:
     * J = 10, P = 0, numérico = score, null = no cuenta.
     */
    protected function effectiveScore(?string $status, $score): ?float
    {
        if ($status === 'J') return 10.0;
        if ($status === 'P') return 0.0;      // <- ahora P cuenta como 0
        if ($score === null) return null;
        return (float) $score;
    }
}
