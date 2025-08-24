<?php

namespace App\Filament\Pages;

use App\Models\GradeEntry;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Week;
use App\Models\Work;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class StudentWeeklyReportAdmin extends Page implements HasForms
{
    use InteractsWithForms;

    /** Usamos la MISMA vista que ven alumnos/papás */
    protected static string $view = 'filament.pages.student-weekly-report';

    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Reporte semanal (admin)';
    protected static ?string $title           = 'Reporte semanal (admin)';

    // Filtros (form)
    public ?int $studentId = null;
    public ?int $weekId    = null;

    // Datos que la vista necesita
    public ?Student $student = null;
    public ?Week $week = null;

    /** Trabajos de la semana del grupo del alumno */
    public Collection $works;

    /** Calificaciones del alumno para esos trabajos (keyBy work_id) */
    public Collection $grades;

    /** Buckets por día */
    public array $byDay = [];

    /** Promedios por materia (semana actual) — ya no lo usa la vista pública, pero lo dejamos */
    public array $subjectAverages = [];

    /** Para la sección de promedios trimestrales en la vista pública */
    public array $termLabels = [
        1 => '1er Trimestre',
        2 => '2do Trimestre',
        3 => '3er Trimestre',
    ];
    public array $allSubjects = [];      // nombres de materias
    public array $termTable   = [];      // [materia][term] => promedio
    public array $termPendings = [];     // [term] => count P
    public array $termZeros    = [];     // [term] => count sin entregar

    /** Listas para tarjetas de pendientes */
    public array $termPWorks    = [];    // [term] => [[name, week, week_starts_at, week_ends_at], ...]
    public array $termZeroWorks = [];    // [term] => [[name, week, week_starts_at, week_ends_at], ...]
    public array $pendingSummary = [];   // [term] => ['withP'=>x, 'withoutP'=>y]

    /** KPIs de la semana actual */
    public ?float $overallAvg = null;     // promedio general (semana)
    public int $progressDelivered = 0;    // entregados (J o score>=1 sin P)
    public int $progressTotal = 0;        // trabajos totales de la semana del grupo del alumno
    public int $progressPending = 0;      // P

    /** Mapeo de días */
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
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'maestro', 'directivo']);
    }

    public static function canAccess(): bool
    {
        return self::shouldRegisterNavigation();
    }

    public function getHeading(): string
    {
        // Mantén este heading arriba del reporte; el contenido del reporte lo pinta la blade pública.
        return 'Reporte semanal (admin)';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('studentId')
                ->label('Alumno')
                ->options(fn () =>
                    Student::query()
                        ->with('group')
                        ->orderBy('paternal_lastname')
                        ->orderBy('maternal_lastname')
                        ->orderBy('names')
                        ->get()
                        ->mapWithKeys(fn (Student $s) => [
                            $s->id => trim("{$s->paternal_lastname} {$s->maternal_lastname}, {$s->names}") .
                                ($s->group ? " — {$s->group->name}" : ''),
                        ])->toArray()
                )
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadData())
                ->required(),

            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->options(fn () =>
                    Week::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Week $w) => [$w->id => $w->name])
                        ->toArray()
                )
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadData())
                ->required(),
        ])->columns(2);
    }

    /** Carga todos los datos que necesita la Blade pública */
    public function loadData(): void
    {
        $this->resetBuckets();

        if (! $this->studentId || ! $this->weekId) {
            return;
        }

        $this->student = Student::with('group')->find($this->studentId);
        $this->week    = Week::find($this->weekId);

        if (! $this->student || ! $this->week || ! $this->student->group_id) {
            return;
        }

        // IDs de asignaciones del grupo del alumno (GST)
        $assignmentIds = GroupSubjectTeacher::where('group_id', $this->student->group_id)->pluck('id');

        // Todos los trabajos de LA SEMANA seleccionada para el grupo del alumno
        $this->works = Work::with(['assignment.subject', 'week'])
            ->where('week_id', $this->weekId)
            ->whereIn('group_subject_teacher_id', $assignmentIds)
            ->orderByRaw("FIELD(weekday,'mon','tue','wed','thu','fri'), id")
            ->get();

        // Calificaciones del alumno para esos trabajos
        $this->grades = GradeEntry::where('student_id', $this->student->id)
            ->whereIn('work_id', $this->works->pluck('id'))
            ->get()
            ->keyBy('work_id');

        // Buckets por día
        foreach ($this->works as $w) {
            $abbr = $w->weekday ?? 'mon';
            if (! isset($this->byDay[$abbr])) {
                $this->byDay[$abbr] = [
                    'abbr'  => $abbr,
                    'label' => $this->dayNames[$abbr] ?? $abbr,
                    'items' => [],
                ];
            }
            $this->byDay[$abbr]['items'][] = $w;
        }

        // KPIs de la semana (overallAvg, delivered / total / pending)
        $this->computeWeekKpis();

        // Promedios trimestrales y pendientes/zero por trimestre
        $this->computeTermData($assignmentIds);
    }

    /** KPIs de la semana seleccionada */
    protected function computeWeekKpis(): void
    {
        $sum = 0.0;
        $count = 0;
        $delivered = 0;
        $pending = 0;

        foreach ($this->works as $w) {
            $g = $this->grades->get($w->id);
            $status = $g->status ?? null;
            $score  = $g->score  ?? null;

            // Entregado: J o score >= 1 sin P
            if ($status === 'J') {
                $delivered++;
            } elseif ($status === 'P') {
                $pending++;
            } else {
                if (!is_null($score) && (float)$score >= 1.0) {
                    $delivered++;
                }
            }

            // Efectivo para promedio: P=0, J=10, normal=score (si null, no cuenta)
            $effective = null;
            if ($status === 'P') {
                $effective = 0.0;
            } elseif ($status === 'J') {
                $effective = 10.0;
            } elseif (!is_null($score)) {
                $effective = (float) $score;
            }

            if (!is_null($effective)) {
                $sum += $effective;
                $count++;
            }
        }

        $this->overallAvg = $count ? round($sum / $count, 2) : null;
        $this->progressDelivered = $delivered;
        $this->progressPending   = $pending;
        $this->progressTotal     = $this->works->count();
    }

    /**
     * Calcula:
     * - Lista de materias del alumno (aunque no tengan trabajos en la semana)
     * - Tabla de promedios por trimestre (sólo con lo que exista en BD; si una materia no tiene calificaciones en el término, se muestra "—")
     * - Pendientes (P) y 0 sin P/J por trimestre, incluyendo nombre de semana y fechas.
     */
    protected function computeTermData(Collection $assignmentIds): void
    {
        // 1) Todas las materias del grupo del alumno
        $subjects = GroupSubjectTeacher::with('subject')
            ->where('group_id', $this->student->group_id)
            ->get()
            ->pluck('subject')
            ->filter()
            ->unique('id')
            ->values();

        $this->allSubjects = $subjects->pluck('name')->toArray();

        // Inicializar estructura de la tabla de promedios [materia][term] => null
        $this->termTable = [];
        foreach ($this->allSubjects as $sName) {
            $this->termTable[$sName] = [1 => null, 2 => null, 3 => null];
        }

        // 2) Traer TODAS las calificaciones del alumno para trabajos del grupo (todas las semanas)
        $groupWorkIds = Work::whereIn('group_subject_teacher_id', $assignmentIds)->pluck('id');
        $entries = GradeEntry::with(['work.assignment.subject', 'work.week'])
            ->where('student_id', $this->student->id)
            ->whereIn('work_id', $groupWorkIds)
            ->get();

        // Buckets por materia/term para promedios
        $buckets = []; // [materia][term] => ['sum'=>, 'count'=>]
        $termPendings = [1 => 0, 2 => 0, 3 => 0];
        $termZeros    = [1 => 0, 2 => 0, 3 => 0];
        $termPWorks   = [1 => [], 2 => [], 3 => []];
        $termZWorks   = [1 => [], 2 => [], 3 => []];

        foreach ($entries as $e) {
            $work = $e->work;
            if (! $work || ! $work->assignment || ! $work->assignment->subject) {
                continue;
            }

            $subjectName = $work->assignment->subject->name ?? 'Materia';
            $termId = (int) ($work->week->trimester_id ?? 0);
            if (!in_array($termId, [1,2,3], true)) {
                continue;
            }

            $status = $e->status ?? null;
            $score  = $e->score  ?? null;

            // Promedio efectivo
            $effective = null;
            if ($status === 'P') {
                $effective = 0.0;
            } elseif ($status === 'J') {
                $effective = 10.0;
            } elseif (!is_null($score)) {
                $effective = (float) $score;
            }

            if (!isset($buckets[$subjectName])) {
                $buckets[$subjectName] = [1 => ['sum'=>0.0,'count'=>0], 2 => ['sum'=>0.0,'count'=>0], 3 => ['sum'=>0.0,'count'=>0]];
            }

            if (!is_null($effective)) {
                $buckets[$subjectName][$termId]['sum']   += $effective;
                $buckets[$subjectName][$termId]['count'] += 1;
            }

            // Pendientes y 0 sin P/J
            if ($status === 'P') {
                $termPendings[$termId]++;

                $termPWorks[$termId][] = [
                    'name'  => $work->name,
                    'week'  => $work->week?->name,
                    'week_starts_at' => $work->week?->starts_at,
                    'week_ends_at'   => $work->week?->ends_at,
                ];
            } else {
                $isZero = (is_null($status) || ($status !== 'P' && $status !== 'J'))
                         && (is_null($score) || (is_numeric($score) && (float)$score === 0.0));
                if ($isZero) {
                    $termZeros[$termId]++;

                    $termZWorks[$termId][] = [
                        'name'  => $work->name,
                        'week'  => $work->week?->name,
                        'week_starts_at' => $work->week?->starts_at,
                        'week_ends_at'   => $work->week?->ends_at,
                    ];
                }
            }
        }

        // Volcar promedios a termTable
        foreach ($this->allSubjects as $sName) {
            if (!isset($buckets[$sName])) {
                continue;
            }
            foreach ([1,2,3] as $t) {
                $sum   = $buckets[$sName][$t]['sum']   ?? 0.0;
                $count = $buckets[$sName][$t]['count'] ?? 0;
                $this->termTable[$sName][$t] = $count ? round($sum / $count, 2) : null;
            }
        }

        $this->termPendings = $termPendings;
        $this->termZeros    = $termZeros;
        $this->termPWorks   = $termPWorks;
        $this->termZeroWorks = $termZWorks;

        $this->pendingSummary = [
            1 => ['withP' => $termPendings[1], 'withoutP' => $termZeros[1]],
            2 => ['withP' => $termPendings[2], 'withoutP' => $termZeros[2]],
            3 => ['withP' => $termPendings[3], 'withoutP' => $termZeros[3]],
        ];
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

        $this->subjectAverages = [];

        $this->overallAvg = null;
        $this->progressDelivered = 0;
        $this->progressPending   = 0;
        $this->progressTotal     = 0;

        $this->allSubjects = [];
        $this->termTable   = [];
        $this->termPendings = [1=>0,2=>0,3=>0];
        $this->termZeros    = [1=>0,2=>0,3=>0];
        $this->termPWorks   = [1=>[],2=>[],3=>[]];
        $this->termZeroWorks = [1=>[],2=>[],3=>[]];
        $this->pendingSummary = [1=>['withP'=>0,'withoutP'=>0],2=>['withP'=>0,'withoutP'=>0],3=>['withP'=>0,'withoutP'=>0]];
    }

    /** Pasamos TODAS las variables que la vista pública consume */
    protected function getViewData(): array
    {
        return [
            'student'          => $this->student,
            'week'             => $this->week,
            'byDay'            => $this->byDay,
            'grades'           => $this->grades,

            // KPIs semana actual
            'overallAvg'       => $this->overallAvg,
            'progressDelivered'=> $this->progressDelivered,
            'progressTotal'    => $this->progressTotal,
            'progressPending'  => $this->progressPending,

            // Sección “Promedio trimestral de trabajos”
            'termLabels'  => $this->termLabels,
            'allSubjects' => $this->allSubjects,
            'termTable'   => $this->termTable,
            'termPendings'=> $this->termPendings,
            'termZeros'   => $this->termZeros,

            // “Trabajos pendientes por trimestre”
            'termPWorks'     => $this->termPWorks,
            'termZeroWorks'  => $this->termZeroWorks,
            'pendingSummary' => $this->pendingSummary,

            // Flags de público (en admin NO)
            'isPublic'      => false,
            'parentViewer'  => null,
            'publicSignUrl' => null,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => $this->studentId && $this->weekId)
                ->extraAttributes(['onclick' => 'window.print()']),

            \Filament\Actions\Action::make('exportPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->visible(fn () => $this->studentId && $this->weekId)
                ->url(fn () => route('admin.reports.student.week.pdf', [
                    'student_id' => $this->studentId,
                    'week_id'    => $this->weekId,
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
