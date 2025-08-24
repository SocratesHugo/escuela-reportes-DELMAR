<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationRuleResource\Pages;
use App\Models\NotificationRule;
use App\Models\SchoolYear;
use App\Models\Group;
use App\Models\Subject;
use App\Services\NotificationRuleEvaluator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class NotificationRuleResource extends Resource
{
    protected static ?string $model = NotificationRule::class;

    protected static ?string $navigationIcon  = 'heroicon-o-bell';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Reglas de notificación';
    protected static ?int    $navigationSort  = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Alcance')->schema([
                Select::make('school_year_id')
                    ->label('Ciclo')
                    ->options(fn () => SchoolYear::orderByDesc('id')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),

                Select::make('group_id')
                    ->label('Grupo')
                    ->options(fn () => Group::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('subject_id', null))
                    ->nullable(),

                Select::make('subject_id')
                    ->label('Materia')
                    ->options(function (callable $get) {
                        $groupId = $get('group_id');
                        if ($groupId) {
                            $rows = DB::table('group_subject_teacher as gst')
                                ->join('subjects as s', 's.id', '=', 'gst.subject_id')
                                ->where('gst.group_id', $groupId)
                                ->select('s.id', 's.name')
                                ->distinct()
                                ->orderBy('s.name')
                                ->get();

                            return $rows->pluck('name', 'id');
                        }
                        return Subject::orderBy('name')->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('name')
                    ->label('Nombre de la regla')
                    ->required(),

                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
            ])->columns(2),

            Section::make('Condiciones')->schema([
                Select::make('trimester')
                    ->label('Trimestre')
                    ->options([1 => 'Trimestre 1', 2 => 'Trimestre 2', 3 => 'Trimestre 3'])
                    ->nullable()
                    ->helperText('Vacío = detectar automáticamente según la fecha actual.'),

                TextInput::make('threshold_pending')
                    ->label('Umbral de Pendientes (P)')
                    ->numeric()
                    ->minValue(0)
                    ->default(4)
                    ->required(),

                TextInput::make('threshold_missing')
                    ->label('Umbral de “Sin entregar” (=0 sin P/J)')
                    ->numeric()
                    ->minValue(0)
                    ->default(3)
                    ->required(),

                TextInput::make('cadence_days')
                    ->label('Frecuencia (días)')
                    ->numeric()
                    ->minValue(1)
                    ->default(15)
                    ->helperText('Cada cuántos días volver a notificar si se mantiene la condición.'),
            ])->columns(2),

            Section::make('Mensaje')->schema([
                TextInput::make('email_subject')
                    ->label('Asunto del correo')
                    ->required(),

                Textarea::make('email_body')
                    ->label('Cuerpo del correo')
                    ->rows(6)
                    ->helperText('Placeholders: {student}, {group}, {trimester}, {pending_count}, {missing_count}, {subjects_list}')
                    ->default("Estimado padre/madre de {student}:\n\nDetectamos {pending_count} pendientes y {missing_count} sin entregar en el {trimester}. Materias: {subjects_list}.\n\nLe pedimos apoyar a su hijo(a) para regularizarse.\n\nAtte."),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Regla')->searchable(),
                TextColumn::make('schoolYear.name')->label('Ciclo')->badge(),
                TextColumn::make('group.name')->label('Grupo')->toggleable(),
                TextColumn::make('subject.name')->label('Materia')->toggleable(),
                TextColumn::make('trimester')->label('Trim.')->sortable(),
                TextColumn::make('threshold_pending')->label('P ≥'),
                TextColumn::make('threshold_missing')->label('0 sin P/J ≥'),
                IconColumn::make('is_active')->label('Activa')->boolean(),
                TextColumn::make('next_run_at')->label('Próxima')->dateTime(),
                TextColumn::make('updated_at')->label('Actualizada')->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('simulate')
                    ->label('Probar ahora')
                    ->icon('heroicon-o-play')
                    ->color('secondary')
                    ->requiresConfirmation()
                    ->action(function (\App\Models\NotificationRule $record) {
                        $evaluator = app(\App\Services\NotificationRuleEvaluator::class);
                        $result = $evaluator->simulate($record);

                        $body = [];
                        $body[] = "Evaluados: {$result['evaluated_count']}";
                        $body[] = "Coincidencias (dispararían): {$result['matched_count']}";
                        if (!empty($result['examples'])) {
                            $body[] = '';
                            $body[] = 'Ejemplos:';
                            foreach ($result['examples'] as $ex) {
                                $body[] = "• {$ex['student']} — P: {$ex['pending']}, 0 sin P/J: {$ex['missing']}, Materias: {$ex['subjects']}";
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Simulación completada')
                            ->body(implode("\n", $body))
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nueva Regla'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNotificationRules::route('/'),
            'create' => Pages\CreateNotificationRule::route('/create'),
            'edit'   => Pages\EditNotificationRule::route('/{record}/edit'),
        ];
    }
}
