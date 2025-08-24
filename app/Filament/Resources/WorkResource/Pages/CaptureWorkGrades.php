<?php

namespace App\Filament\Resources\WorkResource\Pages;

use App\Filament\Resources\WorkResource;
use App\Models\AssignmentWeekState;
use App\Models\GradeEntry;
use App\Models\GroupSubjectTeacher;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Week;
use App\Models\Work;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Jobs\RecomputeWeekAverages;

class CaptureWorkGrades extends Page implements HasForms
{
    use InteractsWithForms;



    protected static string $resource = WorkResource::class;
    protected static ?string $title = 'Captura de calificaciones';
    protected static string $view = 'filament.resources.work-resource.pages.capture-work-grades';

    /** Chips / contexto */
    public ?string $assignmentLabel = null;
    public ?string $weekLabel = null;

    /** Filtros (siempre editables) */
    public ?int $assignmentId = null; // group_subject_teacher_id
    public ?int $weekId       = null;

    /** Matriz y colecciones */
    public array $matrix = [];
    public Collection $students;
    public Collection $works;

    /** Estado de publicación (informativo) */
    public bool $isPublished = false;

    /** Quita el breadcrumb gris */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->students = collect();
        $this->works    = collect();

        // Lee querystring si viene
        $this->assignmentId = request()->integer('assignmentId') ?: $this->assignmentId;
        $this->weekId       = request()->integer('weekId')       ?: $this->weekId;

        // Prellenar form
        $this->form->fill([
            'assignmentId' => $this->assignmentId,
            'weekId'       => $this->weekId,
        ]);

        $this->resetData();
    }

    public function form(Form $form): Form
    {
        // 🔓 Selects SIEMPRE habilitados
        return $form->schema([
            Forms\Components\Select::make('assignmentId')
                ->label('Materia – Grupo')
                ->options(function () {
                    $q    = GroupSubjectTeacher::query()->with(['group','subject']);
                    $user = Auth::user();
                    if ($user && method_exists($user, 'hasRole') && $user->hasRole('maestro')) {
                        $q->where('teacher_id', $user->id);
                    }
                    return $q->get()->mapWithKeys(fn ($a) => [
                        $a->id => "{$a->subject->name} — {$a->group->name}",
                    ])->toArray();
                })
                ->default(fn (self $livewire) => $livewire->assignmentId)
                ->afterStateHydrated(function ($state, callable $set, self $livewire) {
                    if ($livewire->assignmentId && !$state) {
                        $set('assignmentId', $livewire->assignmentId);
                    }
                })
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, self $livewire) {
                    $livewire->assignmentId = (int) $state;
                    $livewire->resetData();
                }),

            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->options(function () {
                    $activeYearId = SchoolYear::active()->value('id')
                        ?? SchoolYear::query()->latest('id')->value('id');
                    return Week::query()
                        ->whereHas('trimester', fn ($q) => $q->where('school_year_id', $activeYearId))
                        ->orderBy('id')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->default(fn (self $livewire) => $livewire->weekId)
                ->afterStateHydrated(function ($state, callable $set, self $livewire) {
                    if ($livewire->weekId && !$state) {
                        $set('weekId', $livewire->weekId);
                    }
                })
                ->required()
                ->reactive()
                ->afterStateUpdated(function ($state, self $livewire) {
                    $livewire->weekId = (int) $state;
                    $livewire->resetData();
                }),
        ])->columns(2);
    }

    /**
     * Carga alumnos y trabajos, y prellena matriz con calificaciones/comentarios existentes.
     */
    public function resetData(): void
    {
        $this->matrix = [];
        $this->assignmentLabel = null;
        $this->weekLabel = null;
        $this->isPublished = false;

        if (!$this->assignmentId || !$this->weekId) {
            $this->students = collect();
            $this->works    = collect();
            return;
        }

        $assignment = GroupSubjectTeacher::with(['group','subject'])->find($this->assignmentId);
        if (!$assignment) {
            $this->students = collect();
            $this->works    = collect();
            return;
        }

        // Chips / labels
        $this->assignmentLabel = "{$assignment->subject->name} — {$assignment->group->name}";
        $this->weekLabel = optional(Week::find($this->weekId))->name;

        // Alumnos del grupo
        $this->students = Student::where('group_id', $assignment->group_id)
            ->orderBy('paternal_lastname')
            ->orderBy('maternal_lastname')
            ->orderBy('names')
            ->get();

        // Trabajos de la semana (ordenados por día y luego id)
        $this->works = Work::where('group_subject_teacher_id', $this->assignmentId)
            ->where('week_id', $this->weekId)
            ->orderBy('weekday')
            ->orderBy('id')
            ->get();

        // Estado de publicación (desde weeks.visible_for_parents)
        $this->isPublished = (bool) Week::whereKey($this->weekId)->value('visible_for_parents');

        // Mantener AssignmentWeekState para trazabilidad (no bloquea edición)
        AssignmentWeekState::firstOrCreate(
            ['group_subject_teacher_id' => $this->assignmentId, 'week_id' => $this->weekId],
            ['is_closed' => false]
        );

        // Prellenar matriz
        $gradeMap = GradeEntry::whereIn('work_id', $this->works->pluck('id'))
            ->whereIn('student_id', $this->students->pluck('id'))
            ->get()
            ->groupBy(fn ($g) => $g->student_id . ':' . $g->work_id);

        foreach ($this->students as $st) {
            foreach ($this->works as $w) {
                $key = $st->id . ':' . $w->id;
                $g = $gradeMap[$key][0] ?? null;

                $this->matrix[$st->id][$w->id] = [
                    'status'  => $g?->status ?? 'normal',
                    'score'   => $g?->score,
                    'comment' => $g?->comment,
                ];
            }
        }
    }

    /** Guardado en bloque de toda la matriz. */
    public function saveMatrix(): void
    {
        if (!$this->assignmentId || !$this->weekId) {
            return;
        }

        foreach ($this->matrix as $studentId => $cols) {
            foreach ($cols as $workId => $payload) {
                $status  = $payload['status'] ?? 'normal';
                $score   = $payload['score']   ?? null;
                $comment = isset($payload['comment']) && $payload['comment'] !== ''
                    ? trim((string) $payload['comment'])
                    : null;

                $status = in_array($status, ['P','J','normal'], true) ? $status : 'normal';

                if ($status === 'normal') {
                    if ($score === '' || $score === null) {
                        $score = null;
                    } else {
                        $score = round(max(0, min(10, (float) $score)), 1);
                    }
                } else {
                    $score = null;
                }

                $entry = GradeEntry::firstOrNew([
                    'work_id'    => (int) $workId,
                    'student_id' => (int) $studentId,
                ]);

                $entry->status  = $status;
                $entry->score   = $score;
                $entry->comment = $comment;
                $entry->save();
            }
        }

        // 🔄 Recalcular promedios inmediatamente
        $this->recomputeSubjectWeeklyAverages();

        Notification::make()
            ->title('Calificaciones guardadas')
            ->success()
            ->send();

        $this->resetData();
    }
        protected function afterCapturePersist(int $weekId): void
    {
        RecomputeWeekAverages::dispatchSync($weekId);
    }
    /**
     * Guardado al vuelo del comentario desde el modal.
     */
    public function saveComment(int $studentId, int $workId): void
    {
        if (!isset($this->matrix[$studentId][$workId])) {
            return;
        }

        $comment = trim((string) ($this->matrix[$studentId][$workId]['comment'] ?? ''));

        $entry = GradeEntry::firstOrNew([
            'work_id'    => $workId,
            'student_id' => $studentId,
        ]);

        // Conserva status/score actuales (de la matriz o del registro existente)
        $status = $this->matrix[$studentId][$workId]['status'] ?? $entry->status ?? 'normal';
        $score  = $this->matrix[$studentId][$workId]['score']  ?? $entry->score;

        $status = in_array($status, ['P','J','normal'], true) ? $status : 'normal';
        if ($status === 'normal') {
            if ($score === '' || $score === null) {
                $score = null;
            } else {
                $score = round(max(0, min(10, (float) $score)), 1);
            }
        } else {
            $score = null;
        }

        $entry->status  = $status;
        $entry->score   = $score;
        $entry->comment = ($comment !== '') ? $comment : null;
        $entry->save();

        // 🔄 Recalcular también tras guardar comentario
        $this->recomputeSubjectWeeklyAverages();

        Notification::make()
            ->title('Comentario guardado')
            ->success()
            ->send();
    }

    /** Acciones de cabecera (ya SIN “Enviar semana”). */
    protected function getHeaderActions(): array
    {
        return [
            // (Decorativo) Acción de filtros
            Actions\Action::make('noopFilters')
                ->label('Editar filtros')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('warning')
                ->disabled(),

            Actions\Action::make('addWorkMon')->label('Nuevo trabajo (Lun)')
                ->action(fn () => $this->quickAddWork(1))
                ->visible(fn () => $this->assignmentId && $this->weekId),

            Actions\Action::make('addWorkTue')->label('Nuevo trabajo (Mar)')
                ->action(fn () => $this->quickAddWork(2))
                ->visible(fn () => $this->assignmentId && $this->weekId),

            Actions\Action::make('addWorkWed')->label('Nuevo trabajo (Mié)')
                ->action(fn () => $this->quickAddWork(3))
                ->visible(fn () => $this->assignmentId && $this->weekId),

            Actions\Action::make('addWorkThu')->label('Nuevo trabajo (Jue)')
                ->action(fn () => $this->quickAddWork(4))
                ->visible(fn () => $this->assignmentId && $this->weekId),

            Actions\Action::make('addWorkFri')->label('Nuevo trabajo (Vie)')
                ->action(fn () => $this->quickAddWork(5))
                ->visible(fn () => $this->assignmentId && $this->weekId),

            Actions\Action::make('save')
                ->label('Guardar todo')
                ->color('primary')
                ->action('saveMatrix')
                ->visible(fn () => $this->assignmentId && $this->weekId),
        ];
    }

    /** Crea una nueva columna (Work) en el día indicado y recarga */
    public function quickAddWork(int $weekday): void
    {
        if (!$this->assignmentId || !$this->weekId) {
            return;
        }
        $weekday = max(1, min(5, (int) $weekday));

        $count = Work::where('group_subject_teacher_id', $this->assignmentId)
            ->where('week_id', $this->weekId)
            ->where('weekday', $weekday)
            ->count();

        Work::create([
            'group_subject_teacher_id' => $this->assignmentId,
            'week_id'                  => $this->weekId,
            'name'                     => 'Trabajo ' . ($count + 1),
            'weekday'                  => $weekday,
            'active'                   => true,
        ]);

        $this->resetData();
    }

    public function getTitle(): string
    {
        return 'Captura semanal (matriz)';
    }

    // ====== Recalculo inmediato del promedio y contadores ======
    protected function recomputeSubjectWeeklyAverages(): void
    {
        if (!$this->assignmentId || !$this->weekId) return;

        // IDs de trabajos de este assignment y semana
        $workIds = Work::where('group_subject_teacher_id', $this->assignmentId)
            ->where('week_id', $this->weekId)
            ->pluck('id');

        // Alumnos del grupo del assignment
        $assignment = GroupSubjectTeacher::find($this->assignmentId);
        if (!$assignment) return;

        $students = Student::where('group_id', $assignment->group_id)->pluck('id');

        foreach ($students as $studentId) {
            // Entradas del alumno para estos trabajos
            $entries = GradeEntry::whereIn('work_id', $workIds)
                ->where('student_id', $studentId)
                ->get();

            $worksCount     = $workIds->count();
            $scoredEntries  = $entries->where('status', 'normal')->whereNotNull('score');
            $scoredCount    = $scoredEntries->count();
            $pendingCount   = $entries->where('status', 'P')->count();
            $justifiedCount = $entries->where('status', 'J')->count();

            $avg = null;
            if ($scoredCount > 0) {
                $avg = round($scoredEntries->avg('score'), 2);
            }

            // Upsert a subject_weekly_averages
            DB::table('subject_weekly_averages')->updateOrInsert(
                [
                    'student_id'               => $studentId,
                    'group_subject_teacher_id' => $this->assignmentId,
                    'week_id'                  => $this->weekId,
                ],
                [
                    'avg'             => $avg,
                    'works_count'     => $worksCount,
                    'scored_count'    => $scoredCount,
                    'pendings_count'  => $pendingCount,
                    'justified_count' => $justifiedCount,
                    'computed_at'     => now(),
                    'updated_at'      => now(),
                    'created_at'      => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }
}
