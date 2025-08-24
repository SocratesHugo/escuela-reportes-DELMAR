<?php

namespace App\Filament\Pages\Admin;

use App\Models\GradeEntry;
use App\Models\Group;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Week;
use App\Models\Work;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class GroupSubjectSnapshotPage extends Page
{
    protected static string $view = 'filament.pages.admin.group-subject-snapshot';

    // Oculto en navegación: se accede por enlace desde el dashboard o URL
    public static function shouldRegisterNavigation(): bool { return false; }

    protected static ?string $navigationIcon  = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationGroup = 'Tableros';
    protected static ?string $navigationLabel = 'Snapshot Grupo–Materia';
    protected static ?string $title           = 'Snapshot Grupo–Materia';

    // Filtros
    public ?int $weekId  = null;
    public ?int $groupId = null;
    public ?int $gstId   = null; // GroupSubjectTeacher id (materia en el grupo)

    // Entidades resueltas
    public ?Week  $week  = null;
    public ?Group $group = null;
    public ?GroupSubjectTeacher $gst = null;

    // Datos
    public Collection $students; // del grupo
    public Collection $works;    // de la semana y gst
    public Collection $grades;   // agrupadas por work_id

    // KPIs
    public int $cellsTotal = 0;
    public int $cellsFilled = 0;
    public int $delivered = 0;   // J o score >= 1
    public int $pendings  = 0;   // P
    public int $zeros     = 0;   // 0 sin P/J
    public float $avg     = 0.0;

    public function mount(): void
    {
        // Leer params si vienen por URL
        $this->weekId  = $this->weekId  ?: (int) request('weekId');
        $this->groupId = $this->groupId ?: (int) request('groupId');
        $this->gstId   = $this->gstId   ?: (int) request('gstId');

        // Semana por defecto: última
        if (!$this->weekId) {
            $this->weekId = Week::max('id') ?: null;
        }

        $this->loadData();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->required()
                ->live()
                ->searchable()
                ->preload()
                ->options(
                    Week::orderByDesc('id')->get()
                        ->mapWithKeys(fn (Week $w) => [
                            $w->id => $w->name.' — '.
                                optional($w->starts_at)->format('Y-m-d').' a '.
                                optional($w->ends_at)->format('Y-m-d')
                        ])->toArray()
                )
                ->afterStateUpdated(fn () => $this->reloadWithFilters()),

            Forms\Components\Select::make('groupId')
                ->label('Grupo')
                ->required()
                ->live()
                ->searchable()
                ->preload()
                ->options(
                    Group::orderBy('name')->pluck('name','id')->toArray()
                )
                ->afterStateUpdated(function () {
                    // Al cambiar grupo, reset de gst para recalcular lista
                    $this->gstId = null;
                    $this->reloadWithFilters();
                }),

            Forms\Components\Select::make('gstId')
                ->label('Materia del grupo')
                ->required()
                ->live()
                ->searchable()
                ->preload()
                ->options(function () {
                    if (!$this->groupId) return [];
                    return GroupSubjectTeacher::where('group_id', $this->groupId)
                        ->with(['subject','teacher'])
                        ->get()
                        ->mapWithKeys(function (GroupSubjectTeacher $gst) {
                            $subject = $gst->subject?->name ?? 'Materia';
                            $teacher = trim(($gst->teacher?->paternal_lastname ?? '') . ' ' . ($gst->teacher?->names ?? ''));
                            return [$gst->id => $subject . ($teacher ? " — {$teacher}" : '')];
                        })->toArray();
                })
                ->afterStateUpdated(fn () => $this->reloadWithFilters()),
        ])->columns(3);
    }

    protected function reloadWithFilters(): void
    {
        // Evitar returns de redirect en métodos void
        $url = route('filament.admin.pages.admin-group-subject-snapshot', array_filter([
            'weekId'  => $this->weekId,
            'groupId' => $this->groupId,
            'gstId'   => $this->gstId,
        ]));
        $this->dispatch('redirect', url: $url);
    }

    public function loadData(): void
    {
        $this->week  = $this->weekId  ? Week::find($this->weekId)   : null;
        $this->group = $this->groupId ? Group::find($this->groupId) : null;
        $this->gst   = $this->gstId   ? GroupSubjectTeacher::with(['subject','teacher','group'])->find($this->gstId) : null;

        $this->students = collect();
        $this->works    = collect();
        $this->grades   = collect();

        $this->cellsTotal = $this->cellsFilled = $this->delivered = $this->pendings = $this->zeros = 0;
        $this->avg = 0.0;

        if (!$this->week || !$this->group) return;

        // Si no hay gst elegido, selecciona el primero del grupo (si existe)
        if (!$this->gst) {
            $this->gst = GroupSubjectTeacher::where('group_id', $this->group->id)->first();
            if ($this->gst) {
                $this->gstId = $this->gst->id;
            } else {
                return;
            }
        }

        // Alumnos del grupo
        $this->students = Student::where('group_id', $this->group->id)
            ->orderBy('paternal_lastname')->orderBy('maternal_lastname')->orderBy('names')
            ->get();

        // Trabajos (semana + gst)
        $this->works = Work::where('week_id', $this->week->id)
            ->where('group_subject_teacher_id', $this->gst->id)
            ->orderByRaw("FIELD(weekday,'mon','tue','wed','thu','fri'), id")
            ->get();

        // Calificaciones
        $this->grades = GradeEntry::whereIn('work_id', $this->works->pluck('id'))->get()->groupBy('work_id');

        // KPIs
        $this->cellsTotal = $this->works->count() * $this->students->count();
        $sum = 0.0; $cnt = 0;

        foreach ($this->works as $w) {
            $ws = $this->grades->get($w->id) ?? collect();
            foreach ($this->students as $stu) {
                $g = $ws->firstWhere('student_id', $stu->id);
                if ($g) {
                    $this->cellsFilled++;

                    if ($g->status === 'J' || (is_numeric($g->score) && (float)$g->score >= 1.0)) {
                        $this->delivered++;
                    }
                    if ($g->status === 'P') $this->pendings++;
                    if ($g->status !== 'P' && $g->status !== 'J' && is_numeric($g->score) && (float)$g->score == 0.0) {
                        $this->zeros++;
                    }

                    if ($g->status === 'J')      { $sum += 10.0; $cnt++; }
                    elseif (is_numeric($g->score)) { $sum += (float)$g->score; $cnt++; }
                }
            }
        }

        $this->avg = $cnt ? round($sum / $cnt, 2) : 0.0;
    }

    public static function getSlug(): string
    {
        return 'admin-group-subject-snapshot';
    }

    public static function getNavigationSort(): ?int
    {
        return 21;
    }
}
