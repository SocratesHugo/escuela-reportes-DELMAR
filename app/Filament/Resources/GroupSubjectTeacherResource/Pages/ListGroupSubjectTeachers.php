<?php

namespace App\Filament\Resources\GroupSubjectTeacherResource\Pages;

use App\Filament\Resources\GroupSubjectTeacherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupSubjectTeachers extends ListRecords
{
    protected static string $resource = GroupSubjectTeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
