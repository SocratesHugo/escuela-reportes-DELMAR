<?php

namespace App\Filament\Pages\Admin;

use App\Models\GradeEntry;
use App\Models\Group;
use App\Models\GroupSubjectTeacher;
use App\Models\Week;
use App\Models\Work;
use Filament\Pages\Page;

class GroupSnapshotPage extends Page
{
    protected static ?string $navigationGroup = 'Dashboard Dirección';
    protected static ?string $navigationIcon  = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Snapshot Grupo';
    protected static string $view             = 'filament.pages.admin.group-snapshot';

    // Filtros por URL
    public ?int $groupId = null;
    public ?int $weekId  = null;

    // Modelos resueltos
    public ?Group $group = null;
    public ?Week  $week  = null;

    /**
     * Cajas por materia
     * [
     *   [
     *     'name' => 'Matemáticas',
     *     'rows' => [
     *        ['name' => 'Matemáticas', 'delivered' => 10, 'pending' => 2, 'zeros' => 1, 'avg' => 8.35],
     *     ],
     *   ],
     *   ...
     * ]
     */
    public array $subjectsBoxes = [];

    public static function shouldRegisterNavigation(): bool
    {
        // Visible para dirección/administración.
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'director', 'coordinador']);
    }

    public function mount(): void
    {
        $this->groupId = (int) request()->integer('groupId');
        $this->weekId  = (int) request()->integer('weekId');

        // Resolver modelos
        $this->group = $this->groupId ? Group::with('students')->find($this->groupId) : null;
        $this->week  = $this->weekId  ? Week::find($this->weekId) : null;

        $this->subjectsBoxes = [];

        if ($this->group && $this->week) {
            $this->loadData();
        }
    }

    protected function loadData(): void
    {
        // 1) Materias del grupo (aunque no haya trabajos)
        $subjects = GroupSubjectTeacher::query()
            ->where('group_id', $this->group->id)
            ->with('subject:id,name')
            ->get()
            ->map(fn ($gst) => $gst->subject?->name)
            ->filter()
            ->unique()
            ->values();

        // 2) Trabajos de la semana de esas materias
        $assignmentIds = GroupSubjectTeacher::where('group_id', $this->group->id)->pluck('id');

        $works = Work::with(['assignment.subject'])
            ->where('week_id', $this->week->id)
            ->whereIn('group_subject_teacher_id', $assignmentIds)
            ->get();

        // 3) Calificaciones de todos los alumnos del grupo para esos trabajos
        $studentIds = $this->group->students->pluck('id');

        $grades = GradeEntry::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('work_id', $works->pluck('id'))
            ->get()
            ->groupBy('work_id');

        // 4) Armar boxes por materia (estandarizando la llave 'rows')
        foreach ($subjects as $subjectName) {
            // Filtrar trabajos de esta materia
            $subjectWorks = $works->filter(function (Work $w) use ($subjectName) {
                $sname = optional($w->assignment?->subject)->name;
                return $sname === $subjectName;
            });

            // Acumular métricas
            $delivered = 0;  // entregados (score > 0 y status != 'P')
            $pending   = 0;  // P
            $zeros     = 0;  // score 0 o null y status != 'P'/'J'
            $sum       = 0.0; // para promedio (P=0, J=10, normal score)
            $count     = 0;

            foreach ($subjectWorks as $w) {
                $entries = $grades->get($w->id) ?? collect();

                foreach ($entries as $g) {
                    $status = $g->status;
                    $score  = is_null($g->score) ? null : (float) $g->score;

                    // Pendientes
                    if ($status === 'P') {
                        $pending++;
                        // P = 0 para promedio
                        $sum += 0.0;
                        $count++;
                        continue;
                    }

                    // Justificados
                    if ($status === 'J') {
                        // J = 10 para promedio
                        $sum += 10.0;
                        $count++;
                        continue;
                    }

                    // Normal: cuenta para promedio si hay score
                    if (!is_null($score)) {
                        $sum += $score;
                        $count++;

                        if ($score > 0) {
                            $delivered++;
                        } else {
                            // score == 0 => no entregado
                            $zeros++;
                        }
                    } else {
                        // score null y sin P/J => sin entregar
                        $zeros++;
                    }
                }
            }

            $avg = $count > 0 ? round($sum / $count, 2) : null;

            $this->subjectsBoxes[] = [
                'name' => $subjectName,
                // Estandarizamos en 'rows'
                'rows' => [[
                    'name'      => $subjectName,
                    'delivered' => $delivered,
                    'pending'   => $pending,
                    'zeros'     => $zeros,
                    'avg'       => $avg,
                ]],
            ];
        }

        // Si no hay materias, al menos un box vacío
        if (empty($this->subjectsBoxes)) {
            $this->subjectsBoxes[] = [
                'name' => '—',
                'rows' => [],
            ];
        }
    }

    // Filament: ocultamos encabezados automáticos
    public function getTitle(): \Illuminate\Contracts\Support\Htmlable|string { return ''; }
    public function getHeading(): \Illuminate\Contracts\Support\Htmlable|string { return ''; }
    public function getHeaderWidgets(): array { return []; }
    protected function getHeaderActions(): array { return []; }
}
