<?php

namespace App\Filament\Resources\AutoCloseRuleResource\Pages;

use App\Filament\Resources\AutoCloseRuleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAutoCloseRules extends ListRecords
{
    protected static string $resource = AutoCloseRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nueva Regla'),
        ];
    }
}
