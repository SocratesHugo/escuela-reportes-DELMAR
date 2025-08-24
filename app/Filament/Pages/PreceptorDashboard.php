<?php

namespace App\Filament\Pages;

use App\Models\Student;
use App\Models\Week;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PreceptorDashboard extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Tableros';
    protected static ?string $navigationLabel = 'Mis preceptuados';

    protected static string $view = 'filament.pages.preceptor-dashboard';

    /** ←↓↓ IMPORTANTE para statePath('data') ↓↓ */
    public array $data = [];

    public ?int $week_id = null;        // El usuario elige
    public Collection $rows;

    public ?string $selectedWeekLabel = null;

    // Estado modal "detalle"
    public ?int $detailStudentId = null;
    public ?string $detailStudentName = null;
    public ?float $detailAvg = null;
    public int $detailPending = 0;
    public int $detailJustified = 0;
    public int $detailMissing = 0;
    public int $detailNormal = 0;
    public array $detailRows = [];

    // Estado modal "sin entregar"
    public ?int $missingStudentId = null;
    public ?string $missingStudentName = null;
    public array $missingRows = [];

    public function mount(): void
    {
        $this->rows = collect();

        // No autoseleccionamos semana; solo prellenamos si llega por URL
        $this->form->fill([
            'week_id' => $this->week_id,
        ]);

        if ($this->week_id) {
            $this->selectedWeekLabel = $this->labelForWeek($this->week_id);
            $this->loadRows();
        }
    }

    /** Por si el state cambia sin el botón */
    public function updated($name, $value): void
    {
        if ($name === 'data.week_id') {
            $this->week_id = $value;
            $this->selectedWeekLabel = $this->labelForWeek($this->week_id);
            $this->loadRows();
        }
    }

    /** Acción del botón "Buscar" */
    public function applyFilter(): void
    {
        // Tomamos el valor actual directamente del state (ya que statePath = data)
        $this->week_id = $this->data['week_id'] ?? $this->week_id;

        $this->selectedWeekLabel = $this->labelForWeek($this->week_id);
        $this->loadRows();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('week_id')
                    ->label('Semana')
                    ->options(function () {
                        return Week::orderByDesc('id')
                            ->get()
                            ->mapWithKeys(function ($w) {
                                $start = $this->fmt($w->starts_at ?? null);
                                $end   = $this->fmt($w->ends_at   ?? null);
                                $name  = $w->name ?: "Semana {$w->id}";
                                $label = trim($name . ($start && $end ? " — {$start} - {$end}" : ''));
                                return [$w->id => $label];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->reactive() // lo dejamos; el botón es plan B
                    ->afterStateUpdated(function (?int $state) {
                        $this->week_id = $state;
                        $this->selectedWeekLabel = $this->labelForWeek($state);
                        $this->loadRows();
                    })
                    ->required(),
            ])
            ->statePath('data'); // ← aquí es donde usamos $this->data
    }

    /** Fecha d/M/Y (01/Sep/2025) */
    protected function fmt($date): ?string
    {
        if (!$date) return null;
        return Carbon::parse($date)->translatedFormat('d/M/Y');
    }

    /** Texto “Semana N — fecha - fecha” usando starts_at/ends_at */
    protected function labelForWeek(?int $weekId): ?string
    {
        if (!$weekId) return null;
        $w = Week::find($weekId);
        if (!$w) return null;

        $start = $this->fmt($w->starts_at ?? null);
        $end   = $this->fmt($w->ends_at   ?? null);
        $name  = $w->name ?: "Semana {$w->id}";

        return trim($name . ($start && $end ? " — {$start} - {$end}" : ''));
    }

    protected function loadRows(): void
    {
        $this->rows = collect();

        if (!$this->week_id) {
            $this->selectedWeekLabel = null;
            return;
        }

        $user = Auth::user();
        $uid  = Auth::id();

        // 1) OBTENER ALUMNOS
        if ($user && $user->hasAnyRole(['admin', 'director', 'coordinador'])) {
            // Admin-level: todos los alumnos
            $students = Student::query()
                ->with('group')
                ->orderBy('paternal_lastname')
                ->orderBy('maternal_lastname')
                ->orderBy('names')
                ->get();
        } else {
            // Maestro/Preceptor:
            // Intento 1: whereHas con relación many-to-many
            $students = Student::query()
                ->whereHas('preceptors', fn ($q) => $q->where('users.id', $uid))
                ->with('group')
                ->orderBy('paternal_lastname')
                ->orderBy('maternal_lastname')
                ->orderBy('names')
                ->get();

            // Intento 2 (fallback): JOIN directo a la pivote
            if ($students->isEmpty()) {
                $students = Student::query()
                    ->join('preceptor_student as ps', 'ps.student_id', '=', 'students.id')
                    ->where('ps.preceptor_id', $uid)            // tu pivote usa preceptor_id
                    ->with('group')
                    ->select('students.*')                       // evita duplicados/colisiones
                    ->distinct()
                    ->orderBy('paternal_lastname')
                    ->orderBy('maternal_lastname')
                    ->orderBy('names')
                    ->get();
            }
        }

        if ($students->isEmpty()) {
            return; // mostrará el mensaje de “no hay alumnos…”
        }

        $studentIds = $students->pluck('id');

        // 2) CONTADORES por alumno en la semana seleccionada
        $counters = DB::table('grade_entries as ge')
            ->join('works as w', 'w.id', '=', 'ge.work_id')
            ->where('w.week_id', $this->week_id)
            ->whereIn('ge.student_id', $studentIds)
            ->selectRaw("
                ge.student_id,
                SUM(ge.status = 'P') AS pending_count,
                SUM(ge.status = 'J') AS justified_count,
                SUM( (ge.score = 0) AND (COALESCE(ge.status, '') NOT IN ('P','J')) ) AS missing_count,
                SUM(LOWER(COALESCE(ge.status, 'normal')) = 'normal') AS normal_count
            ")
            ->groupBy('ge.student_id')
            ->get()
            ->keyBy('student_id');

        // 3) PROMEDIO semanal: P = 0; J con score NULL no afecta
        $averages = DB::table('grade_entries as ge')
            ->join('works as w', 'w.id', '=', 'ge.work_id')
            ->where('w.week_id', $this->week_id)
            ->whereIn('ge.student_id', $studentIds)
            ->selectRaw("
                ge.student_id,
                AVG(CASE WHEN ge.status = 'P' THEN 0 ELSE ge.score END) AS avg_score
            ")
            ->groupBy('ge.student_id')
            ->get()
            ->keyBy('student_id');

        // 4) ARMAR FILAS
        $this->rows = $students->map(function (Student $s) use ($counters, $averages) {
            $c = $counters[$s->id] ?? null;
            $a = $averages[$s->id]->avg_score ?? null;

            return [
                'student_id'      => $s->id,
                'full_name'       => $s->full_name,
                'group'           => optional($s->group)->name ?? '—',
                'avg'             => $a !== null ? (float) $a : null,
                'pending_count'   => (int) ($c->pending_count   ?? 0),
                'justified_count' => (int) ($c->justified_count ?? 0),
                'missing_count'   => (int) ($c->missing_count   ?? 0),
                'normal_count'    => (int) ($c->normal_count    ?? 0),
            ];
        });

        $this->selectedWeekLabel = $this->labelForWeek($this->week_id);
    }

    // ---------- Modales ----------
    public function openDetail(int $studentId): void
    {
        $this->detailStudentId   = $studentId;
        $student                 = Student::with('group')->find($studentId);
        $this->detailStudentName = $student?->full_name ?? 'Alumno';

        $rows = DB::table('grade_entries as ge')
            ->join('works as w', 'w.id', '=', 'ge.work_id')
            ->leftJoin('group_subject_teacher as gst', 'gst.id', '=', 'w.group_subject_teacher_id')
            ->leftJoin('subjects as s', 's.id', '=', 'gst.subject_id')
            ->where('w.week_id', $this->week_id)
            ->where('ge.student_id', $studentId)
            ->select([
                's.name as subject',
                'w.name as work',
                'w.weekday',
                'ge.status',
                'ge.score',
                'ge.comment',
            ])
            ->orderByRaw("FIELD(w.weekday,'mon','tue','wed','thu','fri','sat','sun')")
            ->orderBy('s.name')
            ->get();

        $pending   = 0;
        $justified = 0;
        $missing   = 0;
        $normal    = 0;
        $sumScores = 0.0;
        $numScores = 0;

        $weekdayMap = [
            'mon' => 'Lun','tue' => 'Mar','wed' => 'Mié',
            'thu' => 'Jue','fri' => 'Vie','sat' => 'Sáb','sun' => 'Dom',
        ];

        $this->detailRows = $rows->map(function ($r) use (
            &$pending, &$justified, &$missing, &$normal, &$sumScores, &$numScores, $weekdayMap
        ) {
            $statusRaw  = $r->status ?? 'normal';
            $statusNorm = strtolower($statusRaw);
            $score      = $r->score;

            if ($statusNorm === 'p') {
                $pending++;
            } elseif ($statusNorm === 'j') {
                $justified++;
            } elseif (!is_null($score) && (float) $score === 0.0 && !in_array(strtoupper($statusRaw), ['P','J'], true)) {
                $missing++;
            } else {
                $normal++;
            }

            if (!is_null($score)) {
                $sumScores += (float) $score;
                $numScores++;
            }

            return [
                'subject'  => $r->subject ?? '—',
                'work'     => $r->work ?? '—',
                'weekday'  => $weekdayMap[$r->weekday] ?? strtoupper($r->weekday ?? '—'),
                'status'   => strtoupper($statusRaw),
                'score'    => $score,
                'comment'  => $r->comment,
            ];
        })->toArray();

        $this->detailPending   = $pending;
        $this->detailJustified = $justified;
        $this->detailMissing   = $missing;
        $this->detailNormal    = $normal;
        $this->detailAvg       = $numScores > 0 ? round($sumScores / $numScores, 2) : null;

        $this->dispatch('open-modal', id: 'detailModal');
    }

    public function openMissing(int $studentId): void
    {
        $this->missingStudentId   = $studentId;
        $student                  = Student::find($studentId);
        $this->missingStudentName = $student?->full_name ?? 'Alumno';

        $weekdayMap = [
            'mon' => 'Lun','tue' => 'Mar','wed' => 'Mié',
            'thu' => 'Jue','fri' => 'Vie','sat' => 'Sáb','sun' => 'Dom',
        ];

        $rows = DB::table('grade_entries as ge')
            ->join('works as w', 'w.id', '=', 'ge.work_id')
            ->leftJoin('group_subject_teacher as gst', 'gst.id', '=', 'w.group_subject_teacher_id')
            ->leftJoin('subjects as s', 's.id', '=', 'gst.subject_id')
            ->where('w.week_id', $this->week_id)
            ->where('ge.student_id', $studentId)
            ->where('ge.score', '=', 0)
            ->whereNotIn('ge.status', ['P','J'])
            ->select([
                's.name as subject',
                'w.name as work',
                'w.weekday',
                'ge.comment',
            ])
            ->orderByRaw("FIELD(w.weekday,'mon','tue','wed','thu','fri','sat','sun')")
            ->orderBy('s.name')
            ->get();

        $this->missingRows = $rows->map(fn ($r) => [
            'subject' => $r->subject ?? '—',
            'work'    => $r->work ?? '—',
            'weekday' => $weekdayMap[$r->weekday] ?? strtoupper($r->weekday ?? '—'),
            'comment' => $r->comment,
        ])->toArray();

        $this->dispatch('open-modal', id: 'missingModal');
    }

    public function closeDetail(): void
    {
        $this->dispatch('close-modal', id: 'detailModal');
        $this->detailStudentId   = null;
        $this->detailStudentName = null;
        $this->detailAvg         = null;
        $this->detailPending     = 0;
        $this->detailJustified   = 0;
        $this->detailMissing     = 0;
        $this->detailNormal      = 0;
        $this->detailRows        = [];
    }

    public function closeMissing(): void
    {
        $this->dispatch('close-modal', id: 'missingModal');
        $this->missingStudentId   = null;
        $this->missingStudentName = null;
        $this->missingRows        = [];
    }
}
