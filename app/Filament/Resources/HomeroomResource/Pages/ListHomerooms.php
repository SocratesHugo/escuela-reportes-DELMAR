<?php

namespace App\Filament\Resources\HomeroomResource\Pages;

use App\Filament\Resources\HomeroomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomerooms extends ListRecords
{
    protected static string $resource = HomeroomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New homeroom')
                ->visible(fn () => HomeroomResource::canCreate()),
        ];
    }
}
