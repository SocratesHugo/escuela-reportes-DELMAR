<?php

namespace App\Filament\Resources\WeeklyReportSignatureResource\Pages;

use App\Filament\Resources\WeeklyReportSignatureResource;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyReportSignatures extends ListRecords
{
    protected static string $resource = WeeklyReportSignatureResource::class;

    protected function getHeaderActions(): array
    {
        return []; // Solo lectura
    }
}
