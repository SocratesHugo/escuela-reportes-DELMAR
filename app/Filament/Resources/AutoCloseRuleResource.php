<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutoCloseRuleResource\Pages;
use App\Models\AutoCloseRule;
use App\Models\GroupSubjectTeacher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AutoCloseRuleResource extends Resource
{
    protected static ?string $model = AutoCloseRule::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Reglas de Cierre';
    protected static ?string $navigationGroup = 'Académico';
    protected static ?int    $navigationSort  = 90;

    /** Mostrar en el menú solo a administradores */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /** Refuerzo de permisos dentro del recurso */
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
    public static function canEdit($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
    public static function canDeleteAny(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Programación')
                ->columns(4)
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label('Habilitada')
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(1),

                    Forms\Components\Select::make('weekday')
                        ->label('Día de la semana')
                        ->options([
                            1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
                            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
                        ])
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TimePicker::make('run_time')
                        ->label('Hora (HH:MM)')
                        ->seconds(false)
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Select::make('timezone')
                        ->label('Zona horaria')
                        ->options([
                            'America/Mazatlan'    => 'America/Mazatlan',
                            'America/Mexico_City' => 'America/Mexico_City',
                            'UTC'                  => 'UTC',
                        ])
                        ->default('America/Mazatlan')
                        ->searchable()
                        ->columnSpan(1),

                    Forms\Components\Select::make('group_subject_teacher_id')
                        ->label('Materia–Grupo (opcional)')
                        ->helperText('Si lo dejas vacío, aplica a todas las materias–grupo.')
                        ->options(
                            GroupSubjectTeacher::with(['subject','group'])
                                ->get()
                                ->mapWithKeys(fn ($a) => [$a->id => "{$a->subject->name} — {$a->group->name}"])
                                ->toArray()
                        )
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),

                    Forms\Components\Select::make('close_cutoff')
                        ->label('Criterio de cierre')
                        ->options([
                            'yesterday' => 'Semanas con fin ≤ Ayer',
                            'today'     => 'Semanas con fin ≤ Hoy',
                        ])
                        ->default('yesterday')
                        ->columnSpan(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_enabled')->label('Activa')->boolean(),
                Tables\Columns\TextColumn::make('weekday')->label('Día')
                    ->formatStateUsing(fn ($s) => [1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'][$s] ?? '-'),
                Tables\Columns\TextColumn::make('run_time')->label('Hora'),
                Tables\Columns\TextColumn::make('timezone')->label('TZ'),
                Tables\Columns\TextColumn::make('group_subject_teacher_id')->label('Materia–Grupo')
                    ->getStateUsing(
                        fn (AutoCloseRule $r) => $r->assignment
                            ? ($r->assignment->subject->name.' — '.$r->assignment->group->name)
                            : 'Todas'
                    ),
                Tables\Columns\TextColumn::make('close_cutoff')->label('Cierre'),
                Tables\Columns\TextColumn::make('last_run_at')->label('Última ejecución')->dateTime('d-M-Y H:i'),
                Tables\Columns\TextColumn::make('updated_at')->label('Actualizado')->dateTime('d-M-Y H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionados'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAutoCloseRules::route('/'),
            'create' => Pages\CreateAutoCloseRule::route('/create'),
            'edit'   => Pages\EditAutoCloseRule::route('/{record}/edit'),
        ];
    }
}
