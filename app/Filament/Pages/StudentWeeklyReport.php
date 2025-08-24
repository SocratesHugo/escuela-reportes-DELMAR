<?php

namespace App\Filament\Pages;

use App\Models\GradeEntry;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Week;
use App\Models\Work;
use App\Support\Grades; // 👈 Helper centralizado
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class StudentWeeklyReport extends Page
{
    protected static string $view = 'filament.pages.student-weekly-report';

    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Reporte semanal del estudiante';

    // Flags / contexto público
    public bool $isPublic = false;
    public bool $parentViewer = false;
    public ?string $publicSignUrl = null;

    // Filtros / contexto
    public ?int $studentId = null;
    public ?int $weekId    = null;

    // Datos
    public ?Student $student = null;
    public ?Week $week       = null;

    /** @var \Illuminate\Support\Collection<int,\App\Models\Work> */
    public Collection $works;
    /** @var \Illuminate\Support\Collection<int,\App\Models\GradeEntry> */
    public Collection $grades; // keyBy work_id

    public array $byDay = [];

    // Resúmenes de cabecera
    public ?float $overallAvg = null;
    public int $progressDelivered = 0; // entregados (J o score>=1)
    public int $progressTotal     = 0; // deliverable (mismo que delivered para mostrar 0/0 si no hay entregas)
    public int $progressPending   = 0; // P
    public int $progressMissing   = 0; // 0 sin P/J

    // Trimestral
    public array $termLabels      = [1 => '1er Trimestre', 2 => '2do Trimestre', 3 => '3er Trimestre'];
    public array $allSubjects     = [];
    public array $termTable       = [];  // [subject][termId] => average|null
    public array $termPendings    = [1 => 0, 2 => 0, 3 => 0];
    public array $termZeros       = [1 => 0, 2 => 0, 3 => 0];
    public array $termPWorks      = [1 => [], 2 => [], 3 => []];
    public array $termZeroWorks   = [1 => [], 2 => [], 3 => []];
    public array $pendingSummary  = [1 => ['withP'=>0,'withoutP'=>0], 2 => ['withP'=>0,'withoutP'=>0], 3 => ['withP'=>0,'withoutP'=>0]];

    public ?string $logoDataUri = null;

    public array $dayNames = [
        'mon' => 'Lunes',
        'tue' => 'Martes',
        'wed' => 'Miércoles',
        'thu' => 'Jueves',
        'fri' => 'Viernes',
    ];

    public function mount(): void
    {
        $this->works  = collect();
        $this->grades = collect();
        $this->resetBuckets();

        $req = request();
        $this->isPublic     = $req->boolean('public', false);
        $this->parentViewer = $req->boolean('parent', false);

        $this->studentId = $req->integer('student_id') ?: null;
        $this->weekId    = $req->integer('week_id')    ?: null;

        $emailFromLink   = $req->get('email');

        if ($this->studentId) {
            $this->student = Student::find($this->studentId);
        } elseif (!empty($emailFromLink)) {
            $this->student = Student::where('email', $emailFromLink)->first();
        }

        if (!$this->student && auth()->check() && !$this->isPublic) {
            $userEmail = auth()->user()->email ?? null;
            if ($userEmail) {
                $this->student = Student::where('email', $userEmail)->first();
            }
        }

        if ($this->weekId) {
            $this->week = Week::find($this->weekId);
        } else {
            $this->week = Week::orderByDesc('id')->first();
            $this->weekId = $this->week?->id;
        }

        if (!$this->student || !$this->week) {
            return;
        }

        $this->loadData();

        if ($this->isPublic && $this->parentViewer && $this->student && $this->week) {
            $this->publicSignUrl = route('public.reports.sign', [
                'student_id' => $this->student->id,
                'week_id'    => $this->week->id,
            ]);
        }
    }

    protected function loadData(): void
    {
        // 1) Materias del grupo (todas), para mostrar aunque no haya trabajos esta semana
        $this->allSubjects = GroupSubjectTeacher::query()
            ->where('group_id', $this->student->group_id)
            ->with('subject:id,name')
            ->get()
            ->map(fn ($gst) => $gst->subject?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // 2) Trabajos de la semana del grupo del alumno
        $assignmentIds = GroupSubjectTeacher::where('group_id', $this->student->group_id)->pluck('id');

        $this->works = Work::with(['assignment.subject'])
            ->where('week_id', $this->weekId)
            ->whereIn('group_subject_teacher_id', $assignmentIds)
            ->orderByRaw("FIELD(weekday,'mon','tue','wed','thu','fri'), id")
            ->get();

        // Calificaciones de esta semana
        $this->grades = GradeEntry::where('student_id', $this->student->id)
            ->whereIn('work_id', $this->works->pluck('id'))
            ->get()
            ->keyBy('work_id');

        // 3) Buckets por día y métricas de cabecera
        $this->resetBuckets();

        foreach ($this->works as $w) {
            $abbr = $w->weekday ?? 'mon';
            if (!isset($this->byDay[$abbr])) {
                $this->byDay[$abbr] = [
                    'abbr'  => $abbr,
                    'label' => $this->dayNames[$abbr] ?? $abbr,
                    'items' => [],
                ];
            }
            $this->byDay[$abbr]['items'][] = $w;
        }

        // Progreso (reglas: delivered/J, P, 0-sin-P/J)
        $prog = Grades::progress($this->works, $this->grades);
        $this->progressDelivered = $prog['delivered'];
        $this->progressTotal     = $prog['deliverable']; // mostramos 0/0 cuando no hay entregas
        $this->progressPending   = $prog['pending'];
        $this->progressMissing   = $prog['missing'];

        // Promedio efectivo semanal
        $sum = 0.0; $cnt = 0;
        foreach ($this->works as $w) {
            $g = $this->grades->get($w->id);
            $eff = Grades::effectiveScore($g?->score, $g?->status);
            if (!is_null($eff)) {
                $sum += $eff; $cnt++;
            }
        }
        $this->overallAvg = $cnt ? round($sum / $cnt, 2) : null;

        // 4) Tabla trimestral (dejamos valores en T1 como ejemplo; ajusta si ya tienes cálculo por trimestre)
        foreach ($this->allSubjects as $s) {
            $this->termTable[$s] = [1 => null, 2 => null, 3 => null];
        }

        foreach ($this->allSubjects as $s) {
            $ts = 0.0; $tc = 0;
            foreach ($this->works as $w) {
                $sname = optional($w->assignment?->subject)->name ?? 'Materia';
                if ($sname !== $s) continue;
                $g = $this->grades->get($w->id);
                $eff = Grades::effectiveScore($g?->score, $g?->status);
                if (!is_null($eff)) {
                    $ts += $eff; $tc++;
                }
            }
            $this->termTable[$s][1] = $tc ? round($ts / $tc, 2) : null;
        }

        // Pendientes / No entregados en T1 (para que no quede vacía)
        $this->termPendings[1]   = $this->progressPending;
        $this->termZeros[1]      = $this->progressMissing;
        $this->pendingSummary[1] = ['withP' => $this->progressPending, 'withoutP' => $this->progressMissing];

        if ($this->progressPending > 0 || $this->progressMissing > 0) {
            foreach ($this->works as $w) {
                $g = $this->grades->get($w->id);
                $score  = $g?->score;
                $status = $g?->status;

                if (Grades::isPending($score, $status)) {
                    $this->termPWorks[1][] = [
                        'name'           => $w->name,
                        'week'           => $this->week->name,
                        'week_starts_at' => $this->week->starts_at,
                        'week_ends_at'   => $this->week->ends_at,
                    ];
                } elseif (Grades::isMissingZero($score, $status)) {
                    $this->termZeroWorks[1][] = [
                        'name'           => $w->name,
                        'week'           => $this->week->name,
                        'week_starts_at' => $this->week->starts_at,
                        'week_ends_at'   => $this->week->ends_at,
                    ];
                }
            }
        }
    }

    protected function resetBuckets(): void
    {
        $this->byDay = [
            'mon' => ['abbr' => 'mon', 'label' => $this->dayNames['mon'], 'items' => []],
            'tue' => ['abbr' => 'tue', 'label' => $this->dayNames['tue'], 'items' => []],
            'wed' => ['abbr' => 'wed', 'label' => $this->dayNames['wed'], 'items' => []],
            'thu' => ['abbr' => 'thu', 'label' => $this->dayNames['thu'], 'items' => []],
            'fri' => ['abbr' => 'fri', 'label' => $this->dayNames['fri'], 'items' => []],
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable { return ''; }
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable { return ''; }
    public function getHeaderWidgets(): array { return []; }
    protected function getHeaderActions(): array { return []; }

    protected function getViewData(): array
    {
        return [
            'student'           => $this->student,
            'week'              => $this->week,
            'byDay'             => $this->byDay,
            'grades'            => $this->grades,
            'overallAvg'        => $this->overallAvg,
            'progressDelivered' => $this->progressDelivered,
            'progressTotal'     => $this->progressTotal,
            'progressPending'   => $this->progressPending,
            'progressMissing'   => $this->progressMissing,

            'termLabels'        => $this->termLabels,
            'allSubjects'       => $this->allSubjects,
            'termTable'         => $this->termTable,
            'termPendings'      => $this->termPendings,
            'termZeros'         => $this->termZeros,
            'termPWorks'        => $this->termPWorks,
            'termZeroWorks'     => $this->termZeroWorks,
            'pendingSummary'    => $this->pendingSummary,

            'isPublic'          => $this->isPublic,
            'parentViewer'      => $this->parentViewer,
            'publicSignUrl'     => $this->publicSignUrl,
            'logoDataUri'       => $this->logoDataUri,
        ];
    }
}
