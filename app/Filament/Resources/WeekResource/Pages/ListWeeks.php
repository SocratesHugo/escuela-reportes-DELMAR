<?php

namespace App\Filament\Resources\WeekResource\Pages;

use App\Filament\Resources\WeekResource;
use App\Models\Week;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListWeeks extends ListRecords
{
    protected static string $resource = WeekResource::class;

    /**
     * Botones de cabecera.
     */
    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Solo mostrar al administrador
        if (Auth::user()?->hasRole('admin')) {
            $actions[] = Actions\Action::make('enviarSemana')
                ->label('Enviar / Ocultar Semana')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation() // abre modal de confirmación con nuestro form
                ->modalHeading('Publicar u ocultar una semana')
                ->modalDescription('Esta acción controla la visibilidad de una semana para padres y alumnos.')
                ->modalSubmitActionLabel('Aplicar')
                ->form([
                    Forms\Components\Select::make('week_id')
                        ->label('Semana')
                        ->options(function () {
                            return Week::query()
                                ->orderBy('id')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->required(),

                    Forms\Components\Toggle::make('visible')
                        ->label('Visible para padres y alumnos')
                        ->default(true)
                        ->inline(false),
                ])
                ->action(function (array $data): void {
                    $week = Week::find($data['week_id'] ?? null);

                    if (! $week) {
                        Notification::make()
                            ->title('Semana no encontrada')
                            ->danger()
                            ->send();
                        return;
                    }

                    $week->visible_for_parents = (bool) ($data['visible'] ?? false);
                    $week->save();

                    Notification::make()
                        ->title($week->visible_for_parents
                            ? 'Semana enviada: ahora es visible para padres y alumnos.'
                            : 'Semana oculta para padres y alumnos.')
                        ->success()
                        ->send();

                    // refresca la tabla para ver el cambio si tienes la columna en el recurso
                    $this->refreshTable();
                });
        }

        return $actions;
    }
}
