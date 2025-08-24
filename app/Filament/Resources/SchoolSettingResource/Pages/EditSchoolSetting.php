<?php

namespace App\Filament\Resources\SchoolSettingResource\Pages;

use App\Filament\Resources\SchoolSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditSchoolSetting extends EditRecord
{
    protected static string $resource = SchoolSettingResource::class;

    protected function getHeaderActions(): array
    {
        // Quitamos Delete / Create Another para reforzar singleton
        return [
            // \Filament\Actions\DeleteAction::make(), // <- NO
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Configuración guardada';
    }
}
