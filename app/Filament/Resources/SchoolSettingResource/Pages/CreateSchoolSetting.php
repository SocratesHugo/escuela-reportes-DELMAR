<?php

namespace App\Filament\Resources\SchoolSettingResource\Pages;

use App\Filament\Resources\SchoolSettingResource;
use App\Models\SchoolSetting;
use Filament\Resources\Pages\CreateRecord;

class CreateSchoolSetting extends CreateRecord
{
    protected static string $resource = SchoolSettingResource::class;

    public function mount(): void
    {
        if (SchoolSetting::exists()) {
            $record = SchoolSetting::first();
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]));
            return;
        }

        parent::mount();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Configuración creada';
    }
}
