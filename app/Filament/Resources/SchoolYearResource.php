<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolYearResource\Pages;
use App\Models\SchoolYear;

use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;     // ✅ Filament v3
use Filament\Tables;
use Filament\Tables\Table;   // ✅ Filament v3

class SchoolYearResource extends Resource
{
    protected static ?string $model = SchoolYear::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Ciclos escolares';

    /** Formulario de crear/editar */
    public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\TextInput::make('name')
            ->label('Nombre del ciclo')
            ->required()
            ->maxLength(100),

        Forms\Components\Toggle::make('active')
            ->label('Activo')
            ->helperText('Solo un ciclo puede estar activo a la vez. Al activarlo, los demás se desactivan.')
            ->default(false),
    ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')->label('Ciclo')->sortable()->searchable(),
            Tables\Columns\IconColumn::make('active')->label('Activo')->boolean(),
        ])
        ->filters([
            Tables\Filters\TernaryFilter::make('active')->label('Solo activos'),
        ])
        ->defaultSort('name');
}

    /** Agrupar en el menú (opcional) */
    public static function getNavigationGroup(): ?string
    {
        return 'Catálogos';
    }

    /** Rutas CRUD */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSchoolYears::route('/'),
            'create' => Pages\CreateSchoolYear::route('/create'),
            'edit'   => Pages\EditSchoolYear::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
{
    return (string) \App\Models\SchoolYear::where('active', true)->count();
}
public static function getNavigationBadgeColor(): ?string
{
    return 'success';
}
public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
