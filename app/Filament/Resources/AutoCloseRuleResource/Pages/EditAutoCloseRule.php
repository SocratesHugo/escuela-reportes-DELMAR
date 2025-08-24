<?php

namespace App\Filament\Resources\AutoCloseRuleResource\Pages;

use App\Filament\Resources\AutoCloseRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAutoCloseRule extends EditRecord
{
    protected static string $resource = AutoCloseRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Eliminar'),
        ];
    }
}
