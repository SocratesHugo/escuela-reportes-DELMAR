<?php

namespace App\Filament\Resources\EmailSettingResource\Pages;

use App\Filament\Resources\EmailSettingResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailSettings extends ManageRecords
{
    protected static string $resource = EmailSettingResource::class;

    protected function getHeaderActions(): array
    {
        return []; // 1 sola fila; crea/edita desde la tabla
    }
}
