<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StudentsImport;
use Filament\Notifications\Notification;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        // ⚠️ Nada de returns tempranos
        // ⚠️ Sin visible(), para que SIEMPRE aparezca y probar

        return [
            Actions\CreateAction::make()
                ->label('New Alumno'),

            Actions\Action::make('importStudents')
                ->label('Importar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('secondary')
                ->modalHeading('Importar alumnos desde Excel / CSV')
                ->modalDescription('Encabezados: names, paternal_lastname, maternal_lastname (opcional), email (opcional), group, grade (opcional), school_year.')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Archivo (.xlsx, .xls o .csv)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->maxSize(10240)
                        ->required()
                        ->storeFiles(false),
                ])
                ->action(function (array $data) {
                    /** @var TemporaryUploadedFile $tmp */
                    $tmp = $data['file'];

                    try {
                        $import = new StudentsImport(auth()->user());
                        Excel::import($import, $tmp->getRealPath());

                        Notification::make()
                            ->title('Importación completada')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()
                            ->title('Error al importar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
