<?php

namespace App\Filament\Pages;

use App\Models\GradeEntry;
use App\Models\Group;
use App\Models\GroupSubjectTeacher;
use App\Models\Week;
use App\Models\Work;
use App\Support\Grades;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class SnapshotGroupSubject extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'vista Grupo–Materia';
    protected static string $view = 'filament.pages.snapshot-group-subject';

    public ?int $groupId    = null;
    public ?int $assignment = null; // group_subject_teacher_id
    public ?int $weekId     = null;

    public ?Group $group = null;
    public ?Week  $week  = null;
    public ?GroupSubjectTeacher $gst = null;

    public Collection $works;
    public array $rows = [];

    public function mount(): void
    {
        $this->works = collect();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin','director','coordinador']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('groupId')
                ->label('Grupo')
                ->options(fn() => \App\Models\Group::orderBy('name')->pluck('name','id')->toArray())
                ->searchable()->preload()->reactive()
                ->afterStateUpdated(fn() => $this->resetSelection()),

            Forms\Components\Select::make('assignment')
                ->label('Materia–Grupo')
                ->options(function () {
                    if (!$this->groupId) return [];
                    return GroupSubjectTeacher::with('subject:id,name')
                        ->where('group_id', $this->groupId)
                        ->get()
                        ->mapWithKeys(fn($gst) => [$gst->id => ($gst->subject?->name ?? 'Materia')])
                        ->toArray();
                })
                ->searchable()->preload()->reactive()
                ->afterStateUpdated(fn() => $this->loadData()),

            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->options(fn() => Week::orderBy('id')->pluck('name','id')->toArray())
                ->searchable()->preload()->reactive()
                ->afterStateUpdated(fn() => $this->loadData()),
        ])->columns(3);
    }

    public function resetSelection(): void
    {
        $this->assignment = null;
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->rows = [];
        $this->group = $this->groupId ? Group::find($this->groupId) : null;
        $this->week  = $this->weekId  ? Week::find($this->weekId) : null;
        $this->gst   = $this->assignment ? GroupSubjectTeacher::with('subject')->find($this->assignment) : null;

        if (!$this->group || !$this->week || !$this->gst) return;

        $this->works = Work::where('week_id', $this->week->id)
            ->where('group_subject_teacher_id', $this->gst->id)
            ->get();

        $students = \App\Models\Student::where('group_id', $this->group->id)
            ->orderBy('paternal_lastname')->orderBy('maternal_lastname')->orderBy('names')->get();

        foreach ($students as $s) {
            $grades = GradeEntry::where('student_id', $s->id)
                ->whereIn('work_id', $this->works->pluck('id'))
                ->get()->keyBy('work_id');

            $prog = Grades::progress($this->works, $grades);

            $sum=0.0; $cnt=0;
            foreach ($this->works as $w) {
                $g = $grades->get($w->id);
                $eff = Grades::effectiveScore($g?->score, $g?->status);
                if (!is_null($eff)) { $sum += $eff; $cnt++; }
            }
            $avg = $cnt ? round($sum/$cnt,2) : null;

            $this->rows[] = [
                'student'   => $s,
                'delivered' => $prog['delivered'],
                'pending'   => $prog['pending'],
                'missing'   => $prog['missing'],
                'deliverable' => $prog['deliverable'],
                'avg'       => $avg,
            ];
        }
    }

    protected function getViewData(): array
    {
        return [
            'group' => $this->group,
            'week'  => $this->week,
            'gst'   => $this->gst,
            'rows'  => $this->rows,
        ];
    }
}
