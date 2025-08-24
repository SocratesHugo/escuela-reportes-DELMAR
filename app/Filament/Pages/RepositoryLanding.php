<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class RepositoryLanding extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Repositorio Middle School';
    protected static ?string $title           = 'Repositorio Middle School 2025-2026';
    protected static ?string $slug            = 'repositorio';
    protected static ?int    $navigationSort  = -100; // aparecer primero en el menú
    protected static string  $view            = 'filament.pages.repository-landing';

    /**
     * Enlaces que quieres mostrar en la portada.
     * Puedes editar libremente esta lista (icono, texto y URL).
     */
    public array $links = [
        [
            'label' => 'Calendario escolar',
            'href'  => 'https://calendar.google.com/',
            'icon'  => 'heroicon-m-calendar-days',
        ],
        [
            'label' => 'Lineamientos Middle School',
            'href'  => 'https://drive.google.com/',
            'icon'  => 'heroicon-m-document-text',
        ],
        [
            'label' => 'Repositorio de Formatos',
            'href'  => 'https://drive.google.com/',
            'icon'  => 'heroicon-m-folder',
        ],
        [
            'label' => 'Contacto Dirección',
            'href'  => 'mailto:direccion@escuela.edu',
            'icon'  => 'heroicon-m-envelope',
        ],
        [
            'label' => 'Reporte semanal (admin)',
            'href'  => '/admin/reports/student-week-pdf', // ajusta según tu ruta
            'icon'  => 'heroicon-m-chart-bar',
        ],
        [
            'label' => 'Trabajos',
            'href'  => '/admin/works',
            'icon'  => 'heroicon-m-briefcase',
        ],
        [
            'label' => 'Alumnos',
            'href'  => '/admin/students',
            'icon'  => 'heroicon-m-academic-cap',
        ],
    ];

    public static function shouldRegisterNavigation(): bool
    {
        // Se muestra en el menú (arriba porque $navigationSort es negativo)
        return true;
    }

    /**
     * Si quieres inyectar datos a la vista, puedes hacerlo aquí.
     */
    protected function getViewData(): array
    {
        return [
            'links' => $this->links,
        ];
    }
}
