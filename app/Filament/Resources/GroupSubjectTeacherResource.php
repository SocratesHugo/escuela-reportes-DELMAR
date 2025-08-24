<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupSubjectTeacherResource\Pages;
use App\Models\GroupSubjectTeacher;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;    // v3
use Filament\Tables;
use Filament\Tables\Table;  // v3
use Filament\Forms\Components\Toggle;
use App\Models\SchoolYear;
use App\Models\Group;
use App\Models\Subject;
use Filament\Forms\Get;

class GroupSubjectTeacherResource extends Resource
{
    protected static ?string $model = GroupSubjectTeacher::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Asignaciones';
    protected static ?string $navigationGroup = 'Catálogos';

    public static function form(Form $form): Form
    {
        $activeYearId = fn () => SchoolYear::active()->value('id')
            ?? SchoolYear::query()->latest('id')->value('id');

        return $form->schema([
            // Grupo: solo del ciclo activo
            Forms\Components\Select::make('group_id')
                ->label('Grupo')
                ->options(function () use ($activeYearId) {
                    $yearId = $activeYearId();
                    return Group::query()
                        ->where('school_year_id', $yearId)
                        // ->active()  // quitar esta línea
                        ->orderBy('grade')
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->searchable()->preload()->required()
                ->helperText('Se listan grupos del ciclo activo.'),

            // Materia: del ciclo activo y (si hay grupo) del mismo grado
            Forms\Components\Select::make('subject_id')
                ->label('Materia')
                ->options(function (Get $get) use ($activeYearId) {
                    $yearId = $activeYearId();
                    $grade = null;
                    if ($gid = $get('group_id')) {
                        $grade = Group::find($gid)?->grade;
                    }
                    $q = Subject::query()->where('school_year_id', $yearId);
                    if ($grade) $q->where('grade', $grade);
                    return $q->orderBy('name')->pluck('name','id');
                })
                ->searchable()->preload()->required()
                ->helperText('Filtrada por ciclo activo y el grado del grupo seleccionado.')
                ->reactive(), // se recalcula al cambiar group_id

            // Maestro (usuarios)
            Forms\Components\Select::make('teacher_id')
                ->label('Maestro (usuario)')
                ->relationship('teacher','name')
                ->searchable()->preload()
                ->nullable(),

            Forms\Components\Toggle::make('active')->label('Activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('group.name')->label('Grupo')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('group.grade')->label('Grado')->sortable(),
            Tables\Columns\TextColumn::make('subject.name')->label('Materia')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('teacher.name')->label('Maestro')->placeholder('—'),
            Tables\Columns\IconColumn::make('active')->boolean()->label('Activa'),
        ])
        ->defaultSort('group_id')
            ->filters([])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroupSubjectTeachers::route('/'),
            'create' => Pages\CreateGroupSubjectTeacher::route('/create'),
            'edit' => Pages\EditGroupSubjectTeacher::route('/{record}/edit'),
        ];
    }
    public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
