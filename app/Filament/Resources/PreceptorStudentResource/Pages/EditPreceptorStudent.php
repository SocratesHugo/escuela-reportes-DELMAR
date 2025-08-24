<?php

namespace App\Filament\Resources\PreceptorStudentResource\Pages;

use App\Filament\Resources\PreceptorStudentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPreceptorStudent extends EditRecord
{
    protected static string $resource = PreceptorStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
