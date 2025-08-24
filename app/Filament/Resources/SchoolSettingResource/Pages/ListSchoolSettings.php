<?php

namespace App\Filament\Resources\SchoolSettingResource\Pages;

use App\Filament\Resources\SchoolSettingResource;
use App\Models\SchoolSetting;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListSchoolSettings extends ListRecords
{
    protected static string $resource = SchoolSettingResource::class;

    public function mount(): void
    {
        $record = SchoolSetting::first();
        if (! $record) {
            $record = SchoolSetting::create([
                'school_name'     => null,
                'logo_path'       => null,
                'primary_color'   => null,
                'secondary_color' => null,
                'contact_email'   => null,
            ]);
        }

        // Redirige siempre al edit del único registro
        $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]));
    }

    protected function getHeaderActions(): array
    {
        // Oculta "Create" para reforzar singleton
        return [
            // Actions\CreateAction::make(), // <- NO
        ];
    }
}
