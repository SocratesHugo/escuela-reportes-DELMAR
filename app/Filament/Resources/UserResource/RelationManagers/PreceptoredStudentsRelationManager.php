<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Group;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PreceptoredStudentsRelationManager extends RelationManager
{
    /**
     * Nombre del método de relación en App\Models\User
     */
    protected static string $relationship = 'preceptoredStudents';

    protected static ?string $title = 'Alumnos preceptuados';
    protected static ?string $recordTitleAttribute = 'names';

    /**
     * Mostrar este RelationManager sólo si el usuario tiene rol "preceptor".
     */
    public static function canViewForRecord($ownerRecord, $page): bool
    {
        return method_exists($ownerRecord, 'hasRole') && $ownerRecord->hasRole('preceptor');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            // No editamos alumnos aquí; solo adjuntamos/detach.
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('names')
            ->columns([
                Tables\Columns\TextColumn::make('paternal_lastname')
                    ->label('Apellido paterno')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('maternal_lastname')
                    ->label('Apellido materno')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('names')
                    ->label('Nombres')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Grupo')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Grupo')
                    ->options(fn () => Group::query()->pluck('name', 'id')->toArray()),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Asignar alumno')
                    // Importante: NO usamos relationship('...','full_name').
                    // Definimos el select manualmente y ordenamos por columnas reales:
                    ->recordSelect(function () {
                        return Forms\Components\Select::make('recordId')
                            ->label('Alumno')
                            ->options(
                                Student::query()
                                    ->orderBy('paternal_lastname')
                                    ->orderBy('maternal_lastname')
                                    ->orderBy('names')
                                    ->get()
                                    ->mapWithKeys(function (Student $s) {
                                        $full = trim("{$s->paternal_lastname} {$s->maternal_lastname} {$s->names}");
                                        $group = $s->group?->name ? " — {$s->group->name}" : '';
                                        return [$s->id => $full . $group];
                                    })
                                    ->toArray()
                            )
                            ->searchable()
                            ->preload();
                    })
                    ->preloadRecordSelect()
                    ->modalHeading('Asignar alumno a este preceptor'),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()->label('Quitar'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()->label('Quitar seleccionados'),
            ]);
    }
}
