<?php

namespace App\Filament\Pages\Admin;

use App\Models\Group;
use App\Models\GroupSubjectTeacher;
use App\Models\User;
use App\Models\Week;
use App\Models\Work;
use App\Models\GradeEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;

class WeeklyDashboardPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Tableros';
    protected static ?string $navigationLabel = 'Dashboard semanal';

    protected static string $view = 'filament.pages.admin.weekly-dashboard';

    // Filtros
    public ?int $weekId   = null;   // Requerido
    public ?int $groupId  = null;   // Opcional
    public ?int $gstId    = null;   // Opcional (Materia–Grupo)
    public ?int $teacherId = null;  // Opcional

    // KPIs
    public ?float $kpiAvg = null;
    public float $kpiCoverage = 0.0;   // % celdas capturadas vs esperadas
    public int $kpiDelivered = 0;       // celdas entregadas (no P, no “sin entregar”)
    public int $kpiPending   = 0;       // total P
    public int $kpiZeros     = 0;       // total “sin entregar” (0 sin P/J)
    public float $kpiSignedPct = 0.0;   // % firmas de padres (si luego lo conectas)

    /** @var array<int, array{group:\App\Models\Group,subjects:array<int,array>}> */
    public array $pendingMap = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin','director','coordinador']);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Dashboard semanal (admin)';
    }

    public function mount(): void
    {
        // Semana por defecto: la última
        $this->weekId ??= Week::query()->orderByDesc('id')->value('id');
        $this->reloadData();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->required()
                ->options(
                    Week::orderBy('id')
                        ->get()
                        // Usamos el accessor label (sin horas)
                        ->mapWithKeys(fn (Week $w) => [$w->id => $w->label])
                        ->toArray()
                )
                ->searchable()->preload()->reactive()
                ->afterStateUpdated(fn () => $this->reloadData()),

            Forms\Components\Select::make('groupId')
                ->label('Grupo (opcional)')
                ->options(Group::orderBy('name')->pluck('name', 'id'))
                ->searchable()->preload()->reactive()
                ->afterStateUpdated(function () {
                    // Limpiar materia si el grupo cambia
                    $this->gstId = null;
                    $this->reloadData();
                }),

            Forms\Components\Select::make('gstId')
                ->label('Materia–Grupo (opcional)')
                ->options(function () {
                    $q = GroupSubjectTeacher::query()->with(['subject:id,name', 'group:id,name']);

                    if ($this->groupId) {
                        $q->where('group_id', $this->groupId);
                    }

                    return $q->get()
                        ->mapWithKeys(fn ($gst) => [
                            $gst->id => ($gst->subject?->name ?? 'Materia') . ' — ' . ($gst->group?->name ?? 'Grupo'),
                        ])->toArray();
                })
                ->searchable()->preload()->reactive()
                ->afterStateUpdated(fn () => $this->reloadData()),

            Forms\Components\Select::make('teacherId')
                ->label('Docente (opcional)')
                ->options(
                    User::role('maestro')->orderBy('name')->pluck('name','id')->toArray()
                )
                ->searchable()->preload()->reactive()
                ->afterStateUpdated(fn () => $this->reloadData()),
        ])->columns(4);
    }

    protected function reloadData(): void
    {
        if (!$this->weekId) return;

        // Construir KPIs y mapa
        $this->buildKpisForWeek($this->weekId);
        $this->pendingMap = $this->buildPendingMapForWeek($this->weekId);
    }

    /**
     * KPIs básicos para la semana (respetando filtros opcionales).
     */
    protected function buildKpisForWeek(int $weekId): void
    {
        // Trabajos (Work) filtrados por semana y filtros opcionales
        $worksQ = Work::query()->where('week_id', $weekId);

        if ($this->gstId) {
            $worksQ->where('group_subject_teacher_id', $this->gstId);
        } elseif ($this->groupId) {
            $worksQ->whereHas('groupSubjectTeacher', fn ($q) => $q->where('group_id', $this->groupId));
        }

        if ($this->teacherId) {
            $worksQ->whereHas('groupSubjectTeacher', fn ($q) => $q->where('teacher_id', $this->teacherId));
        }

        $workIds = $worksQ->pluck('id');
        if ($workIds->isEmpty()) {
            $this->kpiAvg = null;
            $this->kpiCoverage = 0.0;
            $this->kpiDelivered = 0;
            $this->kpiPending = 0;
            $this->kpiZeros = 0;
            $this->kpiSignedPct = 0.0;
            return;
        }

        // Calificaciones
        $grades = GradeEntry::query()->whereIn('work_id', $workIds)->get();

        // Cobertura = celdas capturadas / celdas esperadas (aprox)
        $captured = $grades->count();
        // Estimación de esperadas: #alumnos del/los grupos × #trabajos
        $groups = Group::query()
            ->when($this->groupId, fn ($q) => $q->where('id', $this->groupId))
            ->when(!$this->groupId && $this->gstId, fn ($q) => $q->whereHas('groupSubjectTeachers', function ($r) {
                $r->where('id', $this->gstId);
            }))
            ->get();

        $studentsTotal = $groups->sum(fn ($g) => $g->students()->count());
        $expected = max(1, $studentsTotal * $workIds->count());

        $this->kpiCoverage = round(($captured / $expected) * 100, 1);

        // Entregados / Pendientes / 0 sin P/J y Promedio efectivo
        $delivered = 0; $pending = 0; $zeros = 0;
        $sum = 0.0; $cnt = 0;

        foreach ($grades as $g) {
            if ($g->status === 'P') {
                $pending++;
                $sum += 0.0; $cnt++;
                continue;
            }
            if ($g->status === 'J') {
                $sum += 10.0; $cnt++;
                $delivered++;
                continue;
            }
            if (is_numeric($g->score)) {
                $sum += (float) $g->score; $cnt++;
                if ((float)$g->score == 0.0) $zeros++;
                else $delivered++;
            }
        }

        $this->kpiDelivered = $delivered;
        $this->kpiPending   = $pending;
        $this->kpiZeros     = $zeros;
        $this->kpiAvg       = $cnt ? round($sum / $cnt, 2) : null;

        // Firmas de padres (placeholder si aún no guardas firmas)
        $this->kpiSignedPct = 0.0;
    }

    /**
     * Mapa de pendientes por Grupo × Materia con links a snapshots.
     */
    protected function buildPendingMapForWeek(int $weekId): array
    {
        $groups = Group::query()
            ->withCount('students')
            ->when($this->groupId, fn ($q) => $q->where('id', $this->groupId))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($groups as $group) {
            $gsts = GroupSubjectTeacher::query()
                ->with(['subject:id,name','group:id,name'])
                ->where('group_id', $group->id)
                ->when($this->teacherId, fn ($q) => $q->where('teacher_id', $this->teacherId))
                ->get();

            $subjects = [];

            foreach ($gsts as $gst) {
                if ($this->gstId && $gst->id !== $this->gstId) {
                    continue;
                }

                $works = Work::query()
                    ->where('group_subject_teacher_id', $gst->id)
                    ->where('week_id', $weekId)
                    ->pluck('id');

                if ($works->isEmpty()) {
                    continue;
                }

                $groupStudentIds = $group->students()->pluck('id');

                $grades = GradeEntry::query()
                    ->whereIn('work_id', $works)
                    ->whereIn('student_id', $groupStudentIds)
                    ->get();

                $totalCells     = max(1, $grades->count());
                $pendingCount   = $grades->where('status', 'P')->count();

                $sum = 0.0; $cnt = 0;
                foreach ($grades as $g) {
                    if ($g->status === 'P')        { $sum += 0.0;  $cnt++; }
                    elseif ($g->status === 'J')    { $sum += 10.0; $cnt++; }
                    elseif (is_numeric($g->score)) { $sum += (float) $g->score; $cnt++; }
                }

                $subjects[] = [
                    'label'       => $gst->subject?->name ?? 'Materia',
                    'pct_pending' => round(($pendingCount / $totalCells) * 100, 1),
                    'avg'         => $cnt ? round($sum / $cnt, 2) : null,
                    'link_group'   => $this->routeToGroupSnapshot($group->id, $weekId),
                    'link_subject' => $this->routeToGroupSubjectSnapshot($group->id, $gst->id, $weekId),
                ];
            }

            $rows[] = [
                'group'    => $group,
                'subjects' => $subjects,
            ];
        }

        return $rows;
    }

    /** URL al Snapshot de Grupo (todas las materias plegables). */
    protected function routeToGroupSnapshot(int $groupId, int $weekId): ?string
    {
        $name = 'filament.admin.pages.admin-group-snapshot';
        return Route::has($name) ? route($name, ['groupId' => $groupId, 'weekId' => $weekId]) : null;
    }

    /** URL al Snapshot Grupo–Materia. */
    protected function routeToGroupSubjectSnapshot(int $groupId, int $gstId, int $weekId): ?string
    {
        $name = 'filament.admin.pages.admin-group-subject-snapshot';
        return Route::has($name) ? route($name, ['groupId' => $groupId, 'gstId' => $gstId, 'weekId' => $weekId]) : null;
    }

    protected function getViewData(): array
    {
        $week = $this->weekId ? Week::find($this->weekId) : null;

        return [
            // filtros & contexto
            'week'   => $week,

            // KPIs
            'kpiAvg'        => $this->kpiAvg,
            'kpiCoverage'   => $this->kpiCoverage,
            'kpiDelivered'  => $this->kpiDelivered,
            'kpiPending'    => $this->kpiPending,
            'kpiZeros'      => $this->kpiZeros,
            'kpiSignedPct'  => $this->kpiSignedPct,

            // mapa mejorado
            'pendingMap'    => $this->pendingMap,
        ];
    }

    protected function getHeaderActions(): array
    {
        return []; // todo se maneja con el form
    }
}
