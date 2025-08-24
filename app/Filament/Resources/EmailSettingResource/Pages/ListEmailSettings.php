<?php

namespace App\Filament\Resources\EmailSettingResource\Pages;

use App\Filament\Resources\EmailSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmailSettings extends ListRecords
{
    protected static string $resource = EmailSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nueva plantilla')
                ->hidden(fn () => static::getResource()::getModel()::count() > 0), // opcional: permitir sólo 1 registro
        ];
    }
}
