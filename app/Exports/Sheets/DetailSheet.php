<?php

namespace App\Exports\Sheets;

use App\Models\GradeEntry;
use App\Models\Week;
use App\Models\Work;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class DetailSheet implements FromArray, WithTitle
{
    public function __construct(
        public ?int $gstId = null,
        public ?Carbon $dateFrom = null,
        public ?Carbon $dateTo = null,
    ) {}

    public function title(): string
    {
        return 'Detalle';
    }

    public function array(): array
    {
        $rows = [['Grupo','Alumno','Materia','Trimestre','Semana','Fechas semana','Día','Trabajo','Estatus','Calificación','Calificación efectiva','Comentario']];

        $worksQ = Work::query()->with(['assignment.group','assignment.subject','week','grades.student','grades']);
        if ($this->gstId) {
            $worksQ->where('group_subject_teacher_id', $this->gstId);
        }
        if ($this->dateFrom) {
            $worksQ->whereHas('week', fn($q) => $q->whereDate('starts_at', '>=', $this->dateFrom->toDateString()));
        }
        if ($this->dateTo) {
            $worksQ->whereHas('week', fn($q) => $q->whereDate('ends_at', '<=', $this->dateTo->toDateString()));
        }

        $works = $worksQ->orderBy('week_id')->orderByRaw("FIELD(weekday,'mon','tue','wed','thu','fri')")->get();

        foreach ($works as $w) {
            $term   = $this->termOfWeek($w->week);
            $weekNm = $w->week?->name ?? '—';
            $start  = $w->week?->starts_at ? Carbon::parse($w->week->starts_at)->format('Y-m-d') : null;
            $end    = $w->week?->ends_at   ? Carbon::parse($w->week->ends_at)->format('Y-m-d')   : null;
            $range  = ($start && $end) ? ($start.' a '.$end) : '—';
            $day    = $this->weekdayLabel($w->weekday);
            $group  = $w->assignment?->group?->name ?? '—';
            $subj   = $w->assignment?->subject?->name ?? '—';

            foreach ($w->grades as $g) {
                $status = $this->statusLabel($g->status, $g->score);
                $eff    = $this->effective($g->status, $g->score);

                $rows[] = [
                    $group,
                    $g->student?->full_name ?? '—',
                    $subj,
                    $term,
                    $weekNm,
                    $range,
                    $day,
                    $w->name ?? 'Trabajo',
                    $status,
                    is_null($g->score) ? '—' : number_format((float)$g->score, 2),
                    is_null($eff) ? '—' : number_format($eff, 2),
                    $g->comment ?? '—',
                ];
            }
        }

        return $rows;
    }

    protected function weekdayLabel($w): string
    {
        $mapNum = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes'];
        $mapStr = ['mon'=>'Lunes','tue'=>'Martes','wed'=>'Miércoles','thu'=>'Jueves','fri'=>'Viernes'];
        if (is_numeric($w)) return $mapNum[(int)$w] ?? (string)$w;
        if (is_string($w))  return $mapStr[strtolower($w)] ?? ucfirst($w);
        return (string)$w;
        }

    protected function statusLabel(?string $status, ?float $score): string
    {
        if ($status === 'P') return 'P';
        if ($status === 'J') return 'J';
        if (!is_null($score) && (float)$score >= 1.0) return 'Entregado';
        if (!is_null($score) && (float)$score === 0.0) return 'Sin entregar';
        return 'normal';
    }

    protected function effective(?string $status, ?float $score): ?float
    {
        if ($status === 'P') return 0.0;
        if ($status === 'J') return 10.0;
        if ($score === null) return null;
        return (float)$score;
    }

    protected function termOfWeek(?Week $week): int
    {
        if ($week && isset($week->term) && in_array((int)$week->term, [1,2,3])) {
            return (int)$week->term;
        }
        // Ajusta a tu calendario; por defecto 1
        return 1;
    }
}
