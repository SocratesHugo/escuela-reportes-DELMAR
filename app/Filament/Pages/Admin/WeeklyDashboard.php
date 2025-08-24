<?php

namespace App\Filament\Pages\Admin;

use App\Models\GradeEntry;
use App\Models\Group;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Week;
use App\Models\Work;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WeeklyDashboard extends Page
{
    protected static string $view = 'filament.pages.admin.weekly-dashboard';

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Tableros';
    protected static ?string $navigationLabel = 'Dashboard semanal (admin)';
    protected static ?string $title           = 'Dashboard semanal (admin)';

    // filtros
    public ?int $weekId  = null;
    public ?int $groupId = null;

    // data
    public ?Week $week = null;
    public ?Group $group = null;

    public float $avg = 0.0;
    public int $cellsTotal = 0;
    public int $cellsFilled = 0;

    public int $delivered = 0;   // calificados (J cuenta como 10), >=1 también entregado
    public int $pendings  = 0;   // P
    public int $zeros     = 0;   // 0 sin P/J

    public int $signaturesTotal = 0;
    public int $signaturesDone  = 0;

    public array $heatmap = [];           // [groupName => [['subject'=>, 'p_ratio'=>, 'avg'=>]]]
    public array $worstGroups = [];       // top grupos por P
    public array $worstSubjects = [];     // materias con más 0
    public array $lowCoverageTeachers = []; // docentes por % cobertura

    public static function shouldRegisterNavigation(): bool
    {
        $u = auth()->user();
        if (!$u) return false;
        return $u->hasAnyRole(['admin', 'director', 'coordinador']);
    }

    public function mount(): void
    {
        // Semana por defecto
        $this->weekId = $this->weekId ?: (int) request('week_id');
        if (!$this->weekId) {
            $this->weekId = Week::max('id') ?: null;
        }

        $this->groupId = $this->groupId ?: (int) request('group_id') ?: null;

        $this->loadData();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->live()
                ->searchable()
                ->preload()
                ->required()
                ->options(
                    Week::orderByDesc('id')->get()
                        ->mapWithKeys(fn (Week $w) => [$w->id => $w->name . ' — ' .
                            optional($w->starts_at)->translatedFormat('Y-m-d') . ' a ' .
                            optional($w->ends_at)->translatedFormat('Y-m-d')])
                        ->toArray()
                )
                ->afterStateUpdated(fn () => $this->reloadWithFilters()),

            Forms\Components\Select::make('groupId')
                ->label('Grupo (opcional)')
                ->live()
                ->searchable()
                ->preload()
                ->options(
                    Group::orderBy('name')->pluck('name','id')->toArray()
                )
                ->nullable()
                ->afterStateUpdated(fn () => $this->reloadWithFilters()),
        ])->columns(2);
    }

    protected function reloadWithFilters(): void
    {
        // No returns de redirect en métodos void para evitar el fatal error
        $url = route('filament.admin.pages.admin-weekly-dashboard', array_filter([
            'week_id'  => $this->weekId,
            'group_id' => $this->groupId,
        ]));
        // JS redirect; Filament refresca bien
        $this->dispatch('redirect', url: $url);
    }

    protected function loadData(): void
    {
        $this->week  = $this->weekId ? Week::find($this->weekId) : null;
        $this->group = $this->groupId ? Group::find($this->groupId) : null;

        if (!$this->week) {
            // Estado vacío
            $this->resetStats();
            return;
        }

        // Qué GST (materia–grupo) considerar
        $gstQuery = GroupSubjectTeacher::query()->with(['group','subject','teacher']);

        if ($this->group) {
            $gstQuery->where('group_id', $this->group->id);
        }

        /** @var Collection<int,GroupSubjectTeacher> $assignments */
        $assignments = $gstQuery->get();
        if ($assignments->isEmpty()) {
            $this->resetStats();
            return;
        }

        // Trabajos de la semana por GST
        $works = Work::query()
            ->where('week_id', $this->week->id)
            ->whereIn('group_subject_teacher_id', $assignments->pluck('id'))
            ->with(['assignment.group','assignment.subject','assignment.teacher'])
            ->get();

        // Alumnos de los grupos involucrados
        $groupIds = $assignments->pluck('group_id')->unique()->values();
        $students = Student::query()->whereIn('group_id', $groupIds)->get()->keyBy('id');

        // Calificaciones
        $grades = GradeEntry::query()
            ->whereIn('work_id', $works->pluck('id'))
            ->get()
            ->groupBy('work_id');

        // Métricas base de celdas
        $this->cellsTotal  = $works->count() * $students->whereIn('group_id', $groupIds)->count();
        $this->cellsFilled = 0;
        $this->delivered   = 0;
        $this->pendings    = 0;
        $this->zeros       = 0;

        $sum = 0.0; $cnt = 0;

        foreach ($works as $w) {
            $ws = $grades->get($w->id) ?? collect();
            foreach ($students->where('group_id', $w->assignment->group_id) as $stu) {
                $g = $ws->firstWhere('student_id', $stu->id);
                if ($g) {
                    $this->cellsFilled++;

                    // delivered logic (J=10, score>=1 cuenta como entregado)
                    if ($g->status === 'J' || (is_numeric($g->score) && (float)$g->score >= 1)) {
                        $this->delivered++;
                    }

                    if ($g->status === 'P') {
                        $this->pendings++;
                    }

                    if ($g->status !== 'P' && $g->status !== 'J' && is_numeric($g->score) && (float)$g->score == 0.0) {
                        $this->zeros++;
                    }

                    // promedio efectivo
                    if ($g->status === 'J') { $sum += 10.0; $cnt++; }
                    elseif (is_numeric($g->score)) { $sum += (float)$g->score; $cnt++; }
                }
            }
        }
        $this->avg = $cnt ? round($sum / $cnt, 2) : 0.0;

        // Firmas — si tienes la tabla weekly_report_signatures, puedes contarla aquí.
        $this->signaturesTotal = $students->count();
        $this->signaturesDone  = 0; // Integra cuando tengas la tabla poblada

        // Heatmap pendientes por grupo × materia
        $this->heatmap = [];
        $pendingPerGroup = [];
        $zerosPerSubject = [];

        // Prep doc coverage
        $teacherCells = []; // [teacher_id => ['filled'=>x, 'total'=>y]]

        foreach ($assignments as $gst) {
            $groupName   = $gst->group?->name ?? '—';
            $subjectName = $gst->subject?->name ?? 'Materia';
            $teacherId   = $gst->teacher_id;

            $thisGroupStudents = $students->where('group_id', $gst->group_id);
            $worksForGst = $works->where('group_subject_teacher_id', $gst->id);

            $gstTotalCells  = $worksForGst->count() * $thisGroupStudents->count();
            $gstFilledCells = 0;
            $gstP = 0; $gstSum = 0.0; $gstCnt = 0; $gstZeros = 0;

            foreach ($worksForGst as $w) {
                $ws = $grades->get($w->id) ?? collect();
                foreach ($thisGroupStudents as $stu) {
                    $g = $ws->firstWhere('student_id', $stu->id);
                    if ($g) {
                        $gstFilledCells++;
                        if ($g->status === 'P') $gstP++;
                        if ($g->status !== 'P' && $g->status !== 'J' && is_numeric($g->score) && (float)$g->score == 0.0) $gstZeros++;
                        if ($g->status === 'J') { $gstSum += 10.0; $gstCnt++; }
                        elseif (is_numeric($g->score)) { $gstSum += (float)$g->score; $gstCnt++; }
                    }
                }
            }

            $teacherCells[$teacherId]['filled'] = ($teacherCells[$teacherId]['filled'] ?? 0) + $gstFilledCells;
            $teacherCells[$teacherId]['total']  = ($teacherCells[$teacherId]['total']  ?? 0) + $gstTotalCells;

            $this->heatmap[$groupName][] = [
                'subject' => $subjectName,
                'p_ratio' => $gstTotalCells ? round(100 * $gstP / $gstTotalCells, 1) : 0.0,
                'avg'     => $gstCnt ? round($gstSum / $gstCnt, 2) : null,
            ];

            $pendingPerGroup[$groupName] = ($pendingPerGroup[$groupName] ?? 0) + $gstP;
            $zerosPerSubject[$subjectName] = ($zerosPerSubject[$subjectName] ?? 0) + $gstZeros;
        }

        // Top grupos por pendientes
        arsort($pendingPerGroup);
        $this->worstGroups = collect($pendingPerGroup)->take(5)->map(function ($val, $k) {
            $total = max(1, $this->cellsTotal);
            return ['group' => $k, 'p' => $val, 'pct' => round(100*$val/$total, 1)];
        })->values()->all();

        // Materias con más 0
        arsort($zerosPerSubject);
        $this->worstSubjects = collect($zerosPerSubject)->take(5)->map(function ($val, $k) {
            $total = max(1, $this->cellsTotal);
            return ['subject' => $k, 'zeros' => $val, 'pct' => round(100*$val/$total, 1)];
        })->values()->all();

        // Docentes con menor cobertura
        $teachers = Teacher::query()->whereIn('id', array_keys($teacherCells))->get()->keyBy('id');
        $rows = [];
        foreach ($teacherCells as $tid => $stat) {
            $cov = $stat['total'] ? round(100 * $stat['filled'] / $stat['total'], 1) : 0.0;
            $t   = $teachers->get($tid);
            $rows[] = [
                'teacher' => trim(($t->paternal_lastname ?? '').' '.($t->maternal_lastname ?? '').' '.($t->names ?? 'Docente')) ?: 'Docente',
                'coverage'=> $cov,
            ];
        }
        $this->lowCoverageTeachers = collect($rows)->sortBy('coverage')->take(5)->values()->all();
    }

    protected function resetStats(): void
    {
        $this->avg = 0.0;
        $this->cellsTotal = $this->cellsFilled = 0;
        $this->delivered = $this->pendings = $this->zeros = 0;
        $this->signaturesTotal = $this->signaturesDone = 0;
        $this->heatmap = [];
        $this->worstGroups = $this->worstSubjects = $this->lowCoverageTeachers = [];
    }

    public static function getSlug(): string
    {
        return 'admin-weekly-dashboard';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }
}
