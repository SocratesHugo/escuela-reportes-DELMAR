<?php

namespace App\Filament\Pages;

use App\Models\Student;
use App\Models\Week;
use App\Models\GradeEntry;
use App\Models\SubjectWeeklyAverage;
use App\Models\Group;
use App\Models\GroupSubjectTeacher;
use App\Models\Work;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TitularDashboard extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Tableros';
    protected static ?string $navigationLabel = 'Mi grupo (Titular)';
    protected static ?string $title           = 'Reporte del grupo (Titular)';

    protected static string $view             = 'filament.pages.titular-dashboard';

    // Filtros
    public ?int $group_id = null;
    public ?int $week_id  = null;

    // Datos
    /** @var \Illuminate\Support\Collection<int, array> */
    public Collection $rows;

    // Modal detalle por alumno (opcional, similar al de preceptor)
    public bool $detailOpen = false;
    public ?int $detailStudentId = null;
    public ?string $detailStudentName = null;
    /** @var \Illuminate\Support\Collection<int, array> */
    public Collection $detailRows;
    public int $detailPending = 0;
    public int $detailJustified = 0;
    public int $detailNormal = 0;
    public ?float $detailAvg = null;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('titular');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('titular');
    }

    public function mount(): void
    {
        $this->rows = collect();
        $this->detailRows = collect();

        $user = Auth::user();

        // Grupos donde el usuario es titular (vía tabla homerooms)
        $groupIds = DB::table('homerooms')
            ->where('user_id', $user->id)
            ->pluck('group_id')
            ->all();

        // Grupo por defecto (el primero)
        $this->group_id = !empty($groupIds) ? (int) $groupIds[0] : null;

        // Semana visible más reciente
        $this->week_id = Week::query()
            ->where('visible_for_parents', true)
            ->orderByDesc('id')
            ->value('id');

        $this->form->fill([
            'group_id' => $this->group_id,
            'week_id'  => $this->week_id,
        ]);

        $this->loadRows();
    }

    public function form(Form $form): Form
    {
        $user = Auth::user();

        // Opciones de grupo solo para los grupos del titular (homerooms)
        $groupOptions = DB::table('homerooms')
            ->join('groups', 'groups.id', '=', 'homerooms.group_id')
            ->where('homerooms.user_id', $user->id)
            ->orderBy('groups.name')
            ->pluck('groups.name', 'groups.id')
            ->toArray();

        return $form->schema([
            Forms\Components\Select::make('group_id')
                ->label('Grupo')
                ->options($groupOptions)
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->group_id = $state ? (int) $state : null;
                    $this->loadRows();
                    if ($this->detailOpen && $this->detailStudentId) {
                        $this->openDetail($this->detailStudentId);
                    }
                }),

            Forms\Components\Select::make('week_id')
                ->label('Semana')
                ->options(
                    Week::query()
                        ->where('visible_for_parents', true)
                        ->orderBy('id')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->week_id = (int) $state;
                    $this->loadRows();
                    if ($this->detailOpen && $this->detailStudentId) {
                        $this->openDetail($this->detailStudentId);
                    }
                }),
        ])->columns(2);
    }

    /** Carga la tabla de alumnos del grupo con su resumen semanal */
    public function loadRows(): void
    {
        $this->rows = collect();

        if (!$this->group_id || !$this->week_id) {
            return;
        }

        $students = Student::query()
            ->where('group_id', $this->group_id)
            ->orderBy('paternal_lastname')
            ->orderBy('maternal_lastname')
            ->orderBy('names')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $averages = SubjectWeeklyAverage::query()
            ->where('week_id', $this->week_id)
            ->whereIn('student_id', $students->pluck('id'))
            ->select('student_id')
            ->selectRaw('AVG(avg) AS avg_overall')
            ->groupBy('student_id')
            ->pluck('avg_overall', 'student_id');

        $counters = GradeEntry::query()
            ->where('week_id', $this->week_id)
            ->whereIn('student_id', $students->pluck('id'))
            ->select('student_id')
            ->selectRaw("SUM(status = 'P') AS pending_count")
            ->selectRaw("SUM(status = 'J') AS justified_count")
            ->selectRaw("SUM(status = 'NORMAL') AS normal_count")
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $this->rows = $students->map(function (Student $s) use ($averages, $counters) {
            $fullName = trim(($s->paternal_lastname ?? '') . ' ' . ($s->maternal_lastname ?? '') . ' ' . ($s->names ?? ''));
            $avg = $averages[$s->id] ?? null;
            $c   = $counters[$s->id] ?? null;

            return [
                'student_id'      => $s->id,
                'full_name'       => $fullName,
                'avg'             => $avg ? round((float) $avg, 2) : null,
                'pending_count'   => (int)($c?->pending_count ?? 0),
                'justified_count' => (int)($c?->justified_count ?? 0),
                'normal_count'    => (int)($c?->normal_count ?? 0),
            ];
        });
    }

    /** Detalle por alumno (igual que preceptor, pero desde grupo) */
    public function openDetail(int $studentId): void
    {
        $this->detailRows = collect();
        $this->detailPending = $this->detailJustified = $this->detailNormal = 0;
        $this->detailAvg = null;

        $student = Student::with('group')->find($studentId);
        if (!$student || !$this->week_id) {
            $this->detailOpen = false;
            return;
        }

        $this->detailStudentId = $student->id;
        $this->detailStudentName = trim(($student->paternal_lastname ?? '') . ' ' . ($student->maternal_lastname ?? '') . ' ' . ($student->names ?? ''));

        // Promedio del alumno en la semana
        $this->detailAvg = SubjectWeeklyAverage::query()
            ->where('student_id', $student->id)
            ->where('week_id', $this->week_id)
            ->avg('avg');
        if (!is_null($this->detailAvg)) {
            $this->detailAvg = round((float)$this->detailAvg, 2);
        }

        // Trabajos de la semana del grupo del alumno
        $works = Work::query()
            ->where('week_id', $this->week_id)
            ->whereIn('group_subject_teacher_id', function ($q) use ($student) {
                $q->from((new GroupSubjectTeacher)->getTable())
                    ->select('id')
                    ->where('group_id', $student->group_id);
            })
            ->leftJoin('group_subject_teacher as gst', 'gst.id', '=', 'works.group_subject_teacher_id')
            ->leftJoin('subjects as s', 's.id', '=', 'gst.subject_id')
            ->leftJoin('grade_entries as ge', function ($join) use ($student) {
                $join->on('ge.work_id', '=', 'works.id')
                    ->where('ge.student_id', '=', $student->id);
            })
            ->orderByRaw("FIELD(weekday,'mon','tue','wed','thu','fri'), works.id")
            ->get([
                'works.id as work_id',
                'works.name as work_name',
                'works.weekday',
                's.name as subject_name',
                'ge.status',
                'ge.score',
                'ge.comment',
            ]);

        $weekdayMap = [
            'mon' => 'Lun',
            'tue' => 'Mar',
            'wed' => 'Mié',
            'thu' => 'Jue',
            'fri' => 'Vie',
        ];

        $this->detailRows = collect($works)->map(function ($w) use ($weekdayMap) {
            $status = $w->status ?? 'normal';
            return [
                'subject' => $w->subject_name ?? '-',
                'work'    => $w->work_name ?? '-',
                'weekday' => $weekdayMap[$w->weekday] ?? '—',
                'status'  => strtoupper($status),
                'score'   => $w->score,
                'comment' => $w->comment,
            ];
        });

        $this->detailPending   = $this->detailRows->where('status', 'P')->count();
        $this->detailJustified = $this->detailRows->where('status', 'J')->count();
        $this->detailNormal    = $this->detailRows->where('status', 'NORMAL')->count();

        $this->detailOpen = true;
    }

    public function closeDetail(): void
    {
        $this->detailOpen = false;
    }
}
