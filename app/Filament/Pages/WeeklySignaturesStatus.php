<?php

namespace App\Filament\Pages;

use App\Models\Student;
use App\Models\Week;
use App\Models\WeeklyReportSignature;
use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class WeeklySignaturesStatus extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-check-circle';
    protected static ?string $navigationGroup = 'Envíos';
    protected static ?string $navigationLabel = 'Estatus de firmas';
    protected static ?string $title           = 'Estatus de firmas por semana';

    protected static string $view = 'filament.pages.weekly-signatures-status';

    public ?int $weekId  = null;
    public ?int $groupId = null;

    /** @var \Illuminate\Support\Collection */
    public Collection $rows;

    public function mount(): void
    {
        $this->rows = collect();
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->required()
                ->options(fn () => Week::orderByDesc('id')->pluck('name', 'id')->toArray())
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadRows()),

            Forms\Components\Select::make('groupId')
                ->label('Grupo')
                ->options(fn () => \App\Models\Group::orderBy('name')->pluck('name', 'id')->toArray())
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadRows()),
        ])->columns(2);
    }

    public function loadRows(): void
    {
        $this->rows = collect();

        if (!$this->weekId || !$this->groupId) return;

        $students = Student::query()
            ->with('group')
            ->where('group_id', $this->groupId)
            ->orderBy('paternal_lastname')->orderBy('maternal_lastname')->orderBy('names')
            ->get();

        $sig = WeeklyReportSignature::where('week_id', $this->weekId)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $this->rows = $students->map(function ($s) use ($sig) {
            $signed = $sig->get($s->id);
            return [
                'student_id'   => $s->id,
                'student_name' => $s->full_name,
                'group'        => $s->group?->name ?? '—',
                'signed'       => $signed ? true : false,
                'signed_at'    => $signed?->signed_at?->format('Y-m-d H:i') ?? null,
                'parent'       => $signed?->parent_name,
                'email'        => $signed?->parent_email,
            ];
        });
    }

    protected function getViewData(): array
    {
        return [
            'rows' => $this->rows,
        ];
    }
}
