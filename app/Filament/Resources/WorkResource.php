<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkResource\Pages;
use App\Models\Work;
use App\Models\Week;
use App\Models\GroupSubjectTeacher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkResource extends Resource
{
    protected static ?string $model = Work::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Académico';
    protected static ?string $navigationLabel = 'Trabajos';

    /** 🔒 Scope por rol */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if (!$user->hasAnyRole(['admin', 'director', 'coordinador'])) {
            $query->whereHas('groupSubjectTeacher', function (Builder $q) use ($user) {
                $q->where('teacher_id', $user->id); // ⬅️ cambia a 'user_id' si aplica
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $weekdayOptions = [
            'mon' => 'Lunes',
            'tue' => 'Martes',
            'wed' => 'Miércoles',
            'thu' => 'Jueves',
            'fri' => 'Viernes',
        ];

        return $form
            ->schema([
                Forms\Components\Section::make('Datos del trabajo')
                    ->columns(2)
                    ->schema([
                        // Materia–Grupo
                        Forms\Components\Select::make('group_subject_teacher_id')
                            ->label('Materia–Grupo')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                $user = auth()->user();

                                $q = GroupSubjectTeacher::with(['subject','group']);

                                if (!$user?->hasAnyRole(['admin','director','coordinador'])) {
                                    $q->where('teacher_id', $user->id); // ⬅️ cambia a 'user_id' si aplica
                                }

                                return $q->get()
                                    ->mapWithKeys(fn ($gst) => [
                                        $gst->id => ($gst->subject?->name ?? '—') . ' — ' . ($gst->group?->name ?? '—')
                                    ])
                                    ->toArray();
                            }),

                        // Semana (Week::label)
                        Forms\Components\Select::make('week_id')
                            ->label('Semana')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(
                                Week::orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn (Week $w) => [$w->id => $w->label])
                                    ->toArray()
                            ),

                        // 🗓️ Día de la semana
                        Forms\Components\Select::make('weekday')
                            ->label('Día')
                            ->required()
                            ->options($weekdayOptions)
                            ->native(false)
                            ->default('mon'),

                        // Nombre
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre del trabajo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $weekdayOptions = [
            'mon' => 'Lunes',
            'tue' => 'Martes',
            'wed' => 'Miércoles',
            'thu' => 'Jueves',
            'fri' => 'Viernes',
        ];

        return $table
            ->columns([
                // 🗓️ Día
                Tables\Columns\TextColumn::make('weekday')
                    ->label('Día')
                    ->formatStateUsing(fn ($state) => $weekdayOptions[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Trabajo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('week.label')
                    ->label('Semana')
                    ->sortable(),

                Tables\Columns\TextColumn::make('groupSubjectTeacher.subject.name')
                    ->label('Materia'),

                Tables\Columns\TextColumn::make('groupSubjectTeacher.group.name')
                    ->label('Grupo'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d-M-Y H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(function (Work $record): bool {
                        $u = auth()->user();
                        if (!$u) return false;
                        if ($u->hasAnyRole(['admin','director','coordinador'])) return true;
                        return optional($record->groupSubjectTeacher)->teacher_id === $u->id; // ⬅️ cambia a 'user_id' si aplica
                    }),

                Tables\Actions\Action::make('capturar_calificaciones')
                    ->label('Capturar calificaciones')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Work $r) => static::getUrl('capture-work-grades', [
                        'assignmentId' => $r->group_subject_teacher_id,
                        'weekId'       => $r->week_id,
                    ]))
                    ->openUrlInNewTab()
                    ->visible(function (Work $record): bool {
                        $u = auth()->user();
                        if (!$u) return false;
                        if ($u->hasAnyRole(['admin','director','coordinador'])) return true;
                        return optional($record->groupSubjectTeacher)->teacher_id === $u->id; // ⬅️ cambia a 'user_id' si aplica
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(function (Work $record): bool {
                        $u = auth()->user();
                        if (!$u) return false;
                        if ($u->hasAnyRole(['admin','director','coordinador'])) return true;
                        return optional($record->groupSubjectTeacher)->teacher_id === $u->id; // ⬅️ cambia a 'user_id' si aplica
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionados')
                    ->visible(fn() => auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'               => Pages\ListWorks::route('/'),
            'create'              => Pages\CreateWork::route('/create'),
            'edit'                => Pages\EditWork::route('/{record}/edit'),
            'capture-work-grades' => Pages\CaptureWorkGrades::route('/capture-work-grades'),
        ];
    }
}
