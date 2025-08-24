<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubjectResource\Pages;
use App\Models\Subject;
use App\Models\SchoolYear;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;      // v3
use Filament\Tables;
use Filament\Tables\Table;    // v3

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Materias';

    /** Formulario (crear/editar) */
    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Select::make('grade')
                ->label('Grado')
                ->options([
                    7 => '7º',
                    8 => '8º',
                    9 => '9º',
                ])
                ->required(),

            Forms\Components\Select::make('school_year_id')
                ->label('Ciclo escolar')
                ->relationship('schoolYear', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->default(fn () =>
                    SchoolYear::active()->value('id')
                    ?? SchoolYear::query()->latest('id')->value('id')
                ),

            Forms\Components\TextInput::make('name')
                ->label('Nombre de la materia')
                ->required()
                ->maxLength(255),
        ]);
}


    /** Tabla (listado) */
    public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Materia')
                ->searchable()
                ->sortable(),

            // 👇 Nuevo: grado como badge y ordenable
            Tables\Columns\TextColumn::make('grade')
                ->label('Grado')
                ->sortable()
                ->formatStateUsing(fn ($state) => $state ? "{$state}º" : '—')
                ->badge()
                ->color(fn ($state) => match ((int) $state) {
                    7 => 'success',
                    8 => 'info',
                    9 => 'purple',
                    default => 'gray',
                }),

            // (Opcional) mostrar el ciclo escolar
            Tables\Columns\TextColumn::make('schoolYear.name')
                ->label('Ciclo')
                ->toggleable(isToggledHiddenByDefault: true) // se puede activar desde el menú de columnas
                ->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('grade')
                ->label('Grado')
                ->options([7 => '7º', 8 => '8º', 9 => '9º']),
            Tables\Filters\SelectFilter::make('school_year_id')
                ->label('Ciclo')
                ->relationship('schoolYear', 'name'),
        ])
        ->defaultSort('grade');
}


    public static function getNavigationGroup(): ?string
    {
        return 'Catálogos';
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSubjects::route('/'),
            'create' => Pages\CreateSubject::route('/create'),
            'edit'   => Pages\EditSubject::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
{
    $active = SchoolYear::active()->value('name')
        ?? SchoolYear::query()->latest('id')->value('name');

    return $active ? "Ciclo: {$active}" : null;
}

public static function getNavigationBadgeColor(): ?string
{
    return 'info';
}

public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
