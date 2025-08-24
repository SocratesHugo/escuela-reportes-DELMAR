<?php

namespace App\Filament\Resources\HomeroomResource\Pages;

use App\Filament\Resources\HomeroomResource;
use Filament\Resources\Pages\EditRecord;

class EditHomeroom extends EditRecord
{
    protected static string $resource = HomeroomResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
