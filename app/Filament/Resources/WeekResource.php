<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeekResource\Pages; // 👈 IMPORTA EL NAMESPACE CORRECTO
use App\Models\Week;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class WeekResource extends Resource
{
    protected static ?string $model = Week::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Semanas';
    protected static ?string $pluralModelLabel = 'Semanas';
    protected static ?string $modelLabel = 'Semana';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nombre')->required(),
            Forms\Components\DatePicker::make('start_date')->label('Fecha Inicio')->required(),
            Forms\Components\DatePicker::make('end_date')->label('Fecha Fin')->required(),
            Forms\Components\Toggle::make('visible_for_parents')
                ->label('Visible para Padres/Alumnos')
                ->helperText('Solo el administrador debería habilitarla mediante "Enviar Semana".')
                ->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('start_date')->label('Fecha Inicio')->date('Y-m-d'),
                Tables\Columns\TextColumn::make('end_date')->label('Fecha Fin')->date('Y-m-d'),
                Tables\Columns\IconColumn::make('visible_for_parents')
                    ->label('Visible para Padres')
                    ->boolean(),
            ])
            ->actions([
                // Acción solo visible para admin
                Action::make('enviarSemana')
                    ->label('Enviar Semana')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn () => Auth::user() && method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('admin'))
                    ->requiresConfirmation()
                    ->action(function (Week $record) {
                        $record->update(['visible_for_parents' => true]);

                        Notification::make()
                            ->title('Semana enviada (visible para padres y alumnos)')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionadas'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWeeks::route('/'),
            'create' => Pages\CreateWeek::route('/create'),
            'edit'   => Pages\EditWeek::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
