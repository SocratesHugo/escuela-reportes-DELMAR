<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrimesterResource\Pages;
use App\Models\SchoolYear;
use App\Models\Trimester;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;   // ⬅️ FALTABA ESTA LÍNEA
use Filament\Tables;
use Filament\Tables\Table;

class TrimesterResource extends Resource
{
    protected static ?string $model = Trimester::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Trimestres';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ->label('Nombre del trimestre')
                    ->required()
                    ->maxLength(100),

                Forms\Components\DatePicker::make('starts_at')
                    ->label('Inicio')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),

                Forms\Components\DatePicker::make('ends_at')
                    ->label('Fin')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required()
                    ->rule('after:starts_at'),
            ])
            ->columns(2)
            ->mutateFormDataUsing(function (array $data): array {
                if ((empty($data['starts_at']) || empty($data['ends_at'])) && !empty($data['school_year_id'])) {
                    $last = Trimester::where('school_year_id', $data['school_year_id'])
                        ->whereNotNull('ends_at')
                        ->orderByDesc('ends_at')
                        ->first();

                    if ($last && $last->end_date) {
                        $start = $last->end_date->copy()->addDay();
                        $end   = $start->copy()->addMonths(3)->subDay();

                        $data['starts_at'] ??= $start->toDateString();
                        $data['ends_at']   ??= $end->toDateString();
                    }
                }

                return $data;
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('schoolYear.name')
                    ->label('Ciclo')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Trimestre')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Fin')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since(),
            ])
            ->defaultSort('starts_at');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTrimesters::route('/'),
            'create' => Pages\CreateTrimester::route('/create'),
            'edit'   => Pages\EditTrimester::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $active = SchoolYear::active()->value('name');
        return $active ? 'Ciclo: ' . $active : null;
    }

    public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
