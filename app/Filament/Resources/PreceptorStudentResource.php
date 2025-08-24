<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PreceptorStudentResource\Pages;
use App\Filament\Resources\PreceptorStudentResource\RelationManagers;
use App\Models\PreceptorStudent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PreceptorStudentResource extends Resource
{
    protected static ?string $model = PreceptorStudent::class;

    protected static ?string $navigationGroup = 'Asignaciones';
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Preceptores ↔ Alumnos';

public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Select::make('preceptor_id')
            ->label('Preceptor')
            ->relationship(
                name: 'preceptor',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query) =>
                    $query->whereHas('roles', fn ($q) => $q->where('name', 'preceptor'))
            )
            ->searchable()
            ->preload()
            ->required()
            ->helperText('El usuario debe tener rol "preceptor".'),

        Forms\Components\Select::make('student_id')
            ->label('Alumno')
            ->options(fn () => \App\Models\Student::query()
                ->orderBy('paternal_lastname')
                ->orderBy('maternal_lastname')
                ->orderBy('names')
                ->get()
                ->mapWithKeys(fn ($s) => [
                    $s->id => trim("{$s->paternal_lastname} {$s->maternal_lastname} {$s->names}") . ($s->group?->name ? " — {$s->group->name}" : '')
                ])
                ->toArray()
            )
            ->searchable()
            ->preload()
            ->required(),
    ]);
}

public static function table(Table $table): Table
{
    return $table->columns([
        Tables\Columns\TextColumn::make('preceptor.name')->label('Preceptor')->sortable()->searchable(),
        Tables\Columns\TextColumn::make('student.names')
            ->label('Alumno')
            ->formatStateUsing(function ($state, $record) {
                $s = $record->student;
                if (! $s) {
                    return '-';
                }
                $p = $s->paternal_lastname ?? '';
                $m = $s->maternal_lastname ?? '';
                $n = $s->names ?? '';
                return trim("$p $m $n");
            })
            ->searchable(query: function (Builder $query, string $search) {
                $query->whereHas('student', function (Builder $q) use ($search) {
                    $q->where('paternal_lastname', 'like', "%{$search}%")
                      ->orWhere('maternal_lastname', 'like', "%{$search}%")
                      ->orWhere('names', 'like', "%{$search}%");
                });
            }),
    ])->actions([Tables\Actions\EditAction::make()])
      ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
}


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPreceptorStudents::route('/'),
            'create' => Pages\CreatePreceptorStudent::route('/create'),
            'edit' => Pages\EditPreceptorStudent::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
