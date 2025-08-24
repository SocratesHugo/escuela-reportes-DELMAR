<?php

namespace App\Filament\Resources\HomeroomResource\Pages;

use App\Filament\Resources\HomeroomResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeroom extends CreateRecord
{
    protected static string $resource = HomeroomResource::class;

    // La clase base espera string, no ?string
    protected function getRedirectUrl(): string
    {
        // Volver al listado al crear
        return static::getResource()::getUrl('index');
    }
}
