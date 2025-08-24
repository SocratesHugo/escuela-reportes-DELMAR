<?php

namespace App\Filament\Resources\PreceptorStudentResource\Pages;

use App\Filament\Resources\PreceptorStudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPreceptorStudents extends ListRecords
{
    protected static string $resource = PreceptorStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
