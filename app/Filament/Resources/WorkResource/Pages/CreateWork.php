<?php

namespace App\Filament\Resources\WorkResource\Pages;

use App\Filament\Resources\WorkResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWork extends CreateRecord
{
    protected static string $resource = WorkResource::class;

    /**
     * Filament llamará a este método para decidir a dónde ir tras “Create”.
     * Para “Create & create another” NO se llama (se queda el form limpio).
     */
    protected function getRedirectUrl(): string
    {
        // Si ya calculamos una URL específica, úsala; si no, comportamiento por defecto.
        return $this->redirectTo ?? parent::getRedirectUrl();
    }

    protected function afterCreate(): void
    {
        $w = $this->record;

        // Preparamos la URL de la matriz de captura
        $this->redirectTo = WorkResource::getUrl('capture-work-grades', [
            'assignmentId' => $w->group_subject_teacher_id,
            'weekId'       => $w->week_id,
        ]);

        Notification::make()
            ->title('Trabajo creado. Abriendo matriz de captura…')
            ->success()
            ->send();
    }
}
