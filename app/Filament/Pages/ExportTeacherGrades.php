<?php

namespace App\Filament\Pages;

use App\Exports\TeacherGradesExport;
use App\Models\Group;
use App\Models\GroupSubjectTeacher;
use App\Models\Week;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ExportTeacherGrades extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Exportaciones';
    protected static ?string $navigationLabel = 'Exportar calificaciones (Excel)';
    protected static string $view = 'filament.pages.export-teacher-grades';

    // Filtros
    public ?int $groupId     = null;
    public ?int $gstId       = null;   // Materia–Grupo
    public ?int $weekFromId  = null;   // Semana desde
    public ?int $weekToId    = null;   // Semana hasta

    // Matriz por alumno (opcional)
    public ?int $matrixGstId    = null;
    public ?int $matrixGroupId  = null;
    public ?int $matrixTerm     = null; // 1,2,3

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['maestro','admin','director','coordinador']);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ============== Filtros principales ==============
                Forms\Components\Section::make('Filtros principales')
                    ->schema([
                        Forms\Components\Select::make('groupId')
                            ->label('Filtrar por grupo (opcional)')
                            ->options(fn () => Group::orderBy('name')->pluck('name','id')->toArray())
                            ->searchable()
                            ->preload()
                            ->reactive(),

                        Forms\Components\Select::make('gstId')
                            ->label('Filtrar por Materia–Grupo (opcional)')
                            ->options(function () {
                                $u = Auth::user();
                                if (!$u) return [];

                                $q = GroupSubjectTeacher::with(['subject:id,name','group:id,name']);
                                // si es maestro, sólo sus GST
                                if (!$u->hasAnyRole(['admin','director','coordinador'])) {
                                    $q->where('teacher_id', $u->id);
                                }
                                if ($this->groupId) {
                                    $q->where('group_id', $this->groupId);
                                }

                                return $q->get()->mapWithKeys(fn ($gst) => [
                                    $gst->id => ($gst->subject?->name ?? 'Materia') . ' — ' . ($gst->group?->name ?? 'Grupo'),
                                ])->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->reactive(),

                        Forms\Components\Select::make('weekFromId')
                            ->label('Semana desde (opcional)')
                            ->options(fn () =>
                                Week::orderBy('id')->get()->mapWithKeys(fn (Week $w) => [$w->id => $w->label])->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->reactive(),

                        Forms\Components\Select::make('weekToId')
                            ->label('Semana hasta (opcional)')
                            ->options(fn () =>
                                Week::orderBy('id')->get()->mapWithKeys(fn (Week $w) => [$w->id => $w->label])->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->reactive(),
                    ])
                    ->columns(2),

                // ============== Matriz por alumno (opcional) ==============
                Forms\Components\Section::make('Matriz por alumno (opcional)')
                    ->description('Selecciona Materia–Grupo, Grupo y Trimestre para generar una hoja con columnas Trabajo 1..n y Promedio por alumno.')
                    ->schema([
                        Forms\Components\Select::make('matrixGstId')
                            ->label('Materia–Grupo (matriz)')
                            ->options(function () {
                                $u = Auth::user();
                                if (!$u) return [];
                                $q = GroupSubjectTeacher::with(['subject:id,name','group:id,name']);
                                if (!$u->hasAnyRole(['admin','director','coordinador'])) {
                                    $q->where('teacher_id', $u->id);
                                }
                                return $q->get()->mapWithKeys(fn ($x) => [
                                    $x->id => ($x->subject?->name ?? 'Materia') . ' — ' . ($x->group?->name ?? 'Grupo'),
                                ])->toArray();
                            })
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('matrixGroupId')
                            ->label('Grupo (matriz)')
                            ->options(fn () => Group::orderBy('name')->pluck('name','id')->toArray())
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('matrixTerm')
                            ->label('Trimestre (matriz)')
                            ->options([1 => '1er Trimestre', 2 => '2do Trimestre', 3 => '3er Trimestre']),
                    ])
                    ->columns(3),

                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('export')
                        ->label('Exportar a Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->action(function () {
                            $file = 'calificaciones_' . now()->format('Ymd_His') . '.xlsx';

                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\TeacherGradesExport(
                                    gstId:         $this->gstId ?: null,
                                    weekFromId:    $this->weekFromId ?: null,
                                    weekToId:      $this->weekToId ?: null,
                                    matrixGstId:   $this->matrixGstId ?: null,   // <= Materia–Grupo (matriz)
                                    matrixGroupId: $this->matrixGroupId ?: null, // <= Grupo
                                    matrixTerm:    $this->matrixTerm ?: null     // <= Trimestre (1/2/3)
                                ),
                                $file
                            );
                        }),
                ])->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected function getHeaderActions(): array
    {
        return []; // usamos el botón dentro del form
    }
}
