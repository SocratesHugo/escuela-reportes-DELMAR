<?php

namespace App\Filament\Resources\GroupSubjectTeacherResource\Pages;

use App\Filament\Resources\GroupSubjectTeacherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroupSubjectTeacher extends EditRecord
{
    protected static string $resource = GroupSubjectTeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
