<?php

namespace App\Filament\Resources\EmailSettingResource\Pages;

use App\Filament\Resources\EmailSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmailSetting extends EditRecord
{
    protected static string $resource = EmailSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->visible(fn ($record) => true),
        ];
    }
}
