<?php

namespace App\Filament\Pages;

use Filament\Forms\Form;
use Filament\Pages\Page;

class SnapshotGroup extends Page
{
    /** Navegación */
    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Vista por Grupo';

    /**
     * Slug fijo para no romper rutas existentes como
     * route('filament.admin.pages.admin-group-snapshot', ...)
     */
    protected static ?string $slug = 'admin-group-snapshot';

    /** Blade que ya usas para mostrar el snapshot */
    protected static string $view = 'filament.pages.admin.group-snapshot';

    // Si usas estado interno:
    public ?int $groupId = null;
    public ?int $weekId  = null;

    /** No necesitamos más campos aquí */
    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
