<?php

namespace App\Filament\Resources\WorkResource\Pages;

use App\Filament\Resources\WorkResource;
use Filament\Resources\Pages\EditRecord;
use App\Jobs\RecomputeWeekAverages;

class EditWork extends EditRecord
{
    protected static string $resource = WorkResource::class;

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record?->week_id) {
            RecomputeWeekAverages::dispatchSync((int)$record->week_id);
        }
    }
}
