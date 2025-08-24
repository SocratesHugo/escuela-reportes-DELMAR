<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeeklyReportSignatureResource\Pages;
use App\Models\WeeklyReportSignature;
use App\Models\Week;
use App\Models\Student;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class WeeklyReportSignatureResource extends Resource
{
    protected static ?string $model = WeeklyReportSignature::class;

    protected static ?string $navigationGroup = 'Académico';
    protected static ?string $navigationIcon  = 'heroicon-o-pencil-square';
    protected static ?string $navigationLabel = 'Firmas de reportes';
    protected static ?int    $navigationSort  = 98;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([]); // Solo lectura desde la tabla
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('week.name')
                    ->label('Semana')
                    ->formatStateUsing(fn($record) =>
                        $record->week?->name . (
                            $record->week?->starts_at && $record->week?->ends_at
                                ? " — ".$record->week->starts_at->format('Y-m-d')." a ".$record->week->ends_at->format('Y-m-d')
                                : ''
                        )
                    )
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.full_name')
                    ->label('Alumno')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.group.name')
                    ->label('Grupo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('parent_name')
                    ->label('Padre/Madre')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('parent_email')
                    ->label('Email')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('signed_at')
                    ->label('Firmado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user_agent')->label('User-Agent')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('week_id')
                    ->label('Semana')
                    ->options(fn() => Week::orderByDesc('id')->pluck('name','id')->toArray()),

                Tables\Filters\SelectFilter::make('student.group_id')
                    ->label('Grupo')
                    ->relationship('student.group', 'name'),
            ])
            ->actions([])
            ->bulkActions([
                Tables\Actions\ExportBulkAction::make(), // si tienes filament/spatie export
            ])
            ->defaultSort('signed_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWeeklyReportSignatures::route('/'),
        ];
    }
}
