<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Facades\Route;
use App\Models\Week;

class AuditSchoolReports extends Command
{
    protected $signature = 'audit:school';
    protected $description = 'Audita puntos comunes del proyecto (fechas con horas, PSR-4, rutas, etc.)';

    public function handle(): int
    {
        $this->info('== Auditoría del proyecto ==');

        $root = base_path();

        // 1) Buscar formateos con horas
        $this->section('Fechas con horas (posibles sitios a corregir)');
        $patterns = [
            'Y-m-d H:i',
            'Y-m-d\TH:i',
            'H:i',
            'H:i:s',
            'Carbon::parse(',
            '->format(',
        ];
        $this->grep($root, $patterns, ['vendor', 'node_modules', 'storage', 'bootstrap/cache']);

        // 2) Referencias a EmailTemplate (debería ser EmailSetting)
        $this->section('Referencias a EmailTemplate');
        $this->grep($root, ['EmailTemplate'], ['vendor', 'node_modules', 'storage', 'bootstrap/cache']);

        // 3) Clases que más suelen causar PSR-4 (nombre de clase != archivo)
        $this->section('Archivos con posibles desalineaciones PSR-4 (búsqueda heurística)');
        $this->grep($root, [
            'namespace App\\',
            'class ',
        ], ['vendor', 'node_modules', 'storage', 'bootstrap/cache'], true);

        // 4) Rutas requeridas por snapshots
        $this->section('Rutas esperadas por snapshots');
        $expected = [
            'filament.admin.pages.admin-group-snapshot',
            'filament.admin.pages.admin-group-subject-snapshot',
        ];
        foreach ($expected as $name) {
            $ok = Route::has($name);
            $this->line(($ok ? '✔︎' : '✖︎') . " {$name}");
        }

        // 5) Confirmar accessor Week::$label disponible
        $this->section('Accessor Week::$label');
        $week = Week::query()->first();
        if (!$week) {
            $this->warn('No hay registros en weeks para probar el accessor.');
        } else {
            $label = $week->label ?? null;
            if ($label && !str_contains($label, ':')) {
                $this->info('✔︎ Week::$label presente (sin horas): ' . $label);
            } else {
                $this->error('✖︎ Week::$label no parece aplicado o incluye horas: ' . ($label ?? '(null)'));
            }
        }

        $this->newLine();
        $this->comment('Sugerencia: donde haya ->format("Y-m-d H:i") cambia a ->toDateString() o usa $week->label.');
        $this->comment('Al terminar, ejecuta: php artisan optimize:clear');

        return Command::SUCCESS;
    }

    protected function section(string $title): void
    {
        $this->newLine();
        $this->info("— {$title} —");
    }

    /**
     * @param string $root
     * @param array<string> $needles
     * @param array<string> $exclude
     * @param bool $compact  si true, muestra solo rutas y no líneas
     */
    protected function grep(string $root, array $needles, array $exclude = [], bool $compact = false): void
    {
        $finder = (new Finder())
            ->files()
            ->in($root)
            ->name('*.php')
            ->name('*.blade.php');

        foreach ($exclude as $dir) {
            $finder->exclude($dir);
        }

        $hits = 0;

        foreach ($finder as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $content = $file->getContents();

            foreach ($needles as $needle) {
                if (stripos($content, $needle) !== false) {
                    $hits++;
                    if ($compact) {
                        $this->line("• {$path}  (contiene: {$needle})");
                    } else {
                        // Mostrar líneas relevantes
                        $lines = preg_split('/\R/', $content);
                        foreach ($lines as $idx => $line) {
                            if (stripos($line, $needle) !== false) {
                                $n = $idx + 1;
                                $snippet = trim(mb_strimwidth($line, 0, 160, '…'));
                                $this->line("• {$path}:{$n}  {$snippet}");
                            }
                        }
                    }
                }
            }
        }

        if ($hits === 0) {
            $this->line('✓ Nada encontrado.');
        }
    }
}
