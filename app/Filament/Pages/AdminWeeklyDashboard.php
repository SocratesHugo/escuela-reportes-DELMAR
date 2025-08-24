<?php

namespace App\Filament\Pages;

use App\Models\GradeEntry;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Week;
use App\Models\Work;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdminWeeklyDashboard extends Page
{
    protected static string $view = 'filament.pages.admin-weekly-dashboard';

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationGroup = 'Tableros';
    protected static ?string $navigationLabel = 'Dashboard semanal (admin)';

    /** Filtros */
    public ?int $weekId = null;

    /** Datos para la vista */
    public ?Week $week = null;

    public float $kpi_avg           = 0.0;   // Promedio efectivo
    public int   $kpi_delivered     = 0;     // Entregados (score>=1 o J)
    public int   $kpi_pending       = 0;     // P
    public int   $kpi_zero          = 0;     // 0 sin P/J
    public int   $kpi_expected      = 0;     // celdas esperadas (trabajos × alumnos)
    public int   $kpi_captured      = 0;     // celdas capturadas (cualquier score o P/J)
    public float $kpi_coverage_pct  = 0.0;   // cobertura
    public ?float $kpi_sign_pct     = null;  // firmas (%), si hay tabla

    /** Heatmap y tops */
    public array $heatmap = [];      // [groupName][subjectName] => ['pending_pct'=>float, 'avg'=>float]
    public array $top_groups_by_P = [];   // [['group'=>'7A','pending_pct'=>..], ...]
    public array $top_subjects_by_0 = []; // [['subject'=>'Matemáticas','zero_pct'=>..], ...]
    public array $top_teachers_low_coverage = []; // [['teacher'=>'Nombre','coverage'=>..], ...]

    public static function shouldRegisterNavigation(): bool
    {
        $u = auth()->user();
        return $u && $u->hasAnyRole(['admin','director','coordinador']);
    }

    public static function canAccess(): bool
    {
        return self::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        // Semana por defecto = la última
        $this->weekId = $this->weekId ?: Week::query()->max('id');
        $this->loadData();
    }

    /** Filtros del tablero (de momento solo Semana; luego añadimos más sin romper) */
    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->options(Week::orderBy('id')->pluck('name','id'))
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(fn() => $this->loadData())
                ->required(),
        ])->columns(3);
    }

    /** Carga los agregados del tablero */
    public function loadData(): void
    {
        $this->week = $this->weekId ? Week::find($this->weekId) : null;

        // Sin semana -> nada que calcular (la vista muestra un aviso).
        if (!$this->week) {
            $this->resetStats();
            return;
        }

        // 1) Traer todos los trabajos de la semana con relaciones necesarias
        /** @var EloquentCollection<int,Work> $works */
        $works = Work::with([
                'assignment.group',      // -> group.name
                'assignment.subject',    // -> subject.name
            ])
            ->where('week_id', $this->weekId)
            ->get();

        if ($works->isEmpty()) {
            $this->resetStats();
            return;
        }

        // 2) Calcular "expected cells" = sum( alumnos del grupo de cada trabajo )
        $groupIds = $works->pluck('assignment.group.id')->filter()->unique()->values();

        /** @var Collection<int,int> $groupStudentCounts  [group_id => count] */
        $groupStudentCounts = Student::query()
            ->selectRaw('group_id, COUNT(*) as c')
            ->whereIn('group_id', $groupIds)
            ->groupBy('group_id')
            ->pluck('c', 'group_id');

        $expected = 0;
        foreach ($works as $w) {
            $gid = optional($w->assignment?->group)->id;
            $expected += (int) ($groupStudentCounts[$gid] ?? 0);
        }
        $this->kpi_expected = $expected;

        // 3) Grade entries de TODOS los trabajos de la semana
        $workIds = $works->pluck('id');

        /** @var EloquentCollection<int,GradeEntry> $grades */
        $grades = GradeEntry::query()
            ->whereIn('work_id', $workIds)
            ->get(['work_id', 'status', 'score']);

        // KPIs base
        $delivered = 0; $pending = 0; $zero = 0; $captured = 0;
        $sumEff = 0.0; $cntEff = 0;

        foreach ($grades as $g) {
            $status = strtoupper((string)$g->status);
            $score  = is_null($g->score) ? null : (float)$g->score;

            $captured++;

            // Reglas acordadas:
            // - Entregado: score>=1  OR status=='J'
            // - Pendiente: status=='P'
            // - Sin entregar: score==0 AND status not in ('P','J')
            if ($status === 'P') {
                $pending++;
                // Efectivo para promedio: P = 0
                $sumEff += 0.0; $cntEff++;
            } elseif ($status === 'J') {
                $delivered++;
                // J = 10
                $sumEff += 10.0; $cntEff++;
            } else {
                if (!is_null($score)) {
                    if ($score >= 1.0) $delivered++;
                    if ($score == 0.0) $zero++;
                    $sumEff += $score; $cntEff++;
                } else {
                    // no score y sin P/J -> no suma a promedio
                }
            }
        }

        $this->kpi_delivered    = $delivered;
        $this->kpi_pending      = $pending;
        $this->kpi_zero         = $zero;
        $this->kpi_captured     = $captured;
        $this->kpi_avg          = $cntEff > 0 ? round($sumEff / $cntEff, 2) : 0.0;
        $this->kpi_coverage_pct = $this->kpi_expected > 0
            ? round(($this->kpi_captured / $this->kpi_expected) * 100, 1)
            : 0.0;

        // 4) Firmas (%), solo si existe la tabla
        $this->kpi_sign_pct = null;
        if (Schema::hasTable('weekly_report_signatures')) {
            // Firmas por alumno (una por alumno x semana)
            $totalStudents = Student::whereIn('group_id', $groupIds)->count();
            $signed = \DB::table('weekly_report_signatures')
                ->where('week_id', $this->weekId)
                ->count('student_id');

            $this->kpi_sign_pct = $totalStudents > 0
                ? round(($signed / $totalStudents) * 100, 1)
                : 0.0;
        }

        // 5) HEATMAP Grupo × Materia: %P y promedio
        // armamos celdas [group][subject] con contadores
        $grid = []; // [gid][sid] => ['group'=>'7A','subject'=>'Mate','p'=>int,'total'=>int,'sumEff'=>float,'cntEff'=>int]

        foreach ($works as $w) {
            $gid = optional($w->assignment?->group)->id;
            $gname = optional($w->assignment?->group)->name ?? '—';
            $sid = optional($w->assignment?->subject)->id;
            $sname = optional($w->assignment?->subject)->name ?? '—';

            $key = $gid . ':' . $sid;

            if (!isset($grid[$gid]))              $grid[$gid] = [];
            if (!isset($grid[$gid][$sid]))        $grid[$gid][$sid] = ['group'=>$gname,'subject'=>$sname,'p'=>0,'total'=>0,'sumEff'=>0.0,'cntEff'=>0,'zeros'=>0];

            // Para cada trabajo de ese celda, contamos sus grades
            $rows = $grades->where('work_id', $w->id);
            foreach ($rows as $g) {
                $status = strtoupper((string)$g->status);
                $score  = is_null($g->score) ? null : (float)$g->score;

                $grid[$gid][$sid]['total']++;

                if ($status === 'P') {
                    $grid[$gid][$sid]['p']++;
                    $grid[$gid][$sid]['sumEff'] += 0.0;
                    $grid[$gid][$sid]['cntEff']++;
                } elseif ($status === 'J') {
                    $grid[$gid][$sid]['sumEff'] += 10.0;
                    $grid[$gid][$sid]['cntEff']++;
                } else {
                    if (!is_null($score)) {
                        if ($score == 0.0) $grid[$gid][$sid]['zeros']++;
                        $grid[$gid][$sid]['sumEff'] += $score;
                        $grid[$gid][$sid]['cntEff']++;
                    }
                }
            }
        }

        // Normalizamos para la vista
        $heatmap = []; // [groupName][subjectName] => ['pending_pct'=>..., 'avg'=>...]
        foreach ($grid as $gid => $row) {
            foreach ($row as $sid => $cell) {
                $pendingPct = $cell['total'] > 0 ? round(($cell['p'] / $cell['total']) * 100, 1) : 0.0;
                $avg        = $cell['cntEff'] > 0 ? round($cell['sumEff'] / $cell['cntEff'], 2) : 0.0;
                $heatmap[$cell['group']][$cell['subject']] = [
                    'pending_pct' => $pendingPct,
                    'avg'         => $avg,
                ];
            }
        }
        ksort($heatmap); // ordena por grupo
        // también ordenamos materias por nombre en cada fila
        foreach ($heatmap as $g => $cols) {
            ksort($cols);
            $heatmap[$g] = $cols;
        }
        $this->heatmap = $heatmap;

        // 6) TOP alertas
        // TOP grupos por %P (sumamos P/total de sus celdas)
        $groupsAgg = [];
        foreach ($grid as $gid => $row) {
            $p = 0; $t = 0;
            foreach ($row as $cell) { $p += $cell['p']; $t += $cell['total']; }
            $groupsAgg[] = [
                'group' => $row[array_key_first($row)]['group'] ?? '—',
                'pending_pct' => $t>0 ? round(($p/$t)*100,1) : 0.0,
            ];
        }
        usort($groupsAgg, fn($a,$b) => $b['pending_pct'] <=> $a['pending_pct']);
        $this->top_groups_by_P = array_slice($groupsAgg, 0, 5);

        // TOP materias por %0 (sumamos zeros/total)
        $subjectsAgg = [];
        $tmp = []; // [subject => ['zeros'=>..,'total'=>..]]
        foreach ($grid as $row) {
            foreach ($row as $cell) {
                $s = $cell['subject'];
                if (!isset($tmp[$s])) $tmp[$s] = ['zeros'=>0,'total'=>0];
                $tmp[$s]['zeros'] += $cell['zeros'];
                $tmp[$s]['total'] += $cell['total'];
            }
        }
        foreach ($tmp as $s => $agg) {
            $subjectsAgg[] = [
                'subject' => $s,
                'zero_pct'=> $agg['total']>0 ? round(($agg['zeros']/$agg['total'])*100,1) : 0.0,
            ];
        }
        usort($subjectsAgg, fn($a,$b) => $b['zero_pct'] <=> $a['zero_pct']);
        $this->top_subjects_by_0 = array_slice($subjectsAgg, 0, 5);

        // TOP docentes por baja cobertura (capturados/esperados de sus GST en la semana)
        // mapeo gst_id => teacher_id
        $gstMap = GroupSubjectTeacher::query()
            ->whereIn('id', $works->pluck('group_subject_teacher_id')->unique())
            ->with(['teacher:id,name,email'])
            ->get()
            ->keyBy('id');

        $byTeacher = []; // [teacher => ['cap'=>..,'exp'=>..]]
        foreach ($works as $w) {
            $gst   = $gstMap[$w->group_subject_teacher_id] ?? null;
            $tName = $gst?->teacher?->name ?? ("Docente #" . ($gst?->teacher_id ?? '—'));
            $gid   = optional($w->assignment?->group)->id;
            $exp   = (int)($groupStudentCounts[$gid] ?? 0);

            if (!isset($byTeacher[$tName])) $byTeacher[$tName] = ['cap'=>0,'exp'=>0];

            $byTeacher[$tName]['exp'] += $exp;
            // capturados = grade entries de ese work
            $byTeacher[$tName]['cap'] += $grades->where('work_id', $w->id)->count();
        }
        $rows = [];
        foreach ($byTeacher as $t => $agg) {
            $rows[] = [
                'teacher'  => $t,
                'coverage' => $agg['exp']>0 ? round(($agg['cap']/$agg['exp'])*100,1) : 0.0,
            ];
        }
        usort($rows, fn($a,$b) => $a['coverage'] <=> $b['coverage']); // menor primero
        $this->top_teachers_low_coverage = array_slice($rows, 0, 5);
    }

    protected function resetStats(): void
    {
        $this->kpi_avg = 0.0;
        $this->kpi_delivered = 0;
        $this->kpi_pending = 0;
        $this->kpi_zero = 0;
        $this->kpi_expected = 0;
        $this->kpi_captured = 0;
        $this->kpi_coverage_pct = 0.0;
        $this->kpi_sign_pct = null;

        $this->heatmap = [];
        $this->top_groups_by_P = [];
        $this->top_subjects_by_0 = [];
        $this->top_teachers_low_coverage = [];
    }

    /** Ocultamos encabezados automáticos de Filament */
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable { return ''; }
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable { return ''; }
    public function getHeaderWidgets(): array { return []; }
    protected function getHeaderActions(): array { return []; }
}
