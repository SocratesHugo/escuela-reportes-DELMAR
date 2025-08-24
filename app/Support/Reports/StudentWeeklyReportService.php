<?php

namespace App\Support\Reports;

use App\Models\GradeEntry;
use App\Models\GroupSubjectTeacher;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Trimester;
use App\Models\Week;
use App\Models\Work;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class StudentWeeklyReportService
{
    /**
     * Construye toda la data del reporte semanal/trimestral para un alumno y semana.
     *
     * @return array{
     *   student:\App\Models\Student,
     *   week:\App\Models\Week|null,
     *   currentTrimester:\App\Models\Trimester|null,
     *   logoUrl:?string,
     *   overallAvg:?float,
     *   progressTotal:int,
     *   progressDelivered:int,
     *   progressPendingP:int,           // P
     *   progressNotDeliveredZero:int,   // 0 sin P
     *   progressLabels:array<string>,
     *   byDay:array<string,array{label:string,items:array<int,\App\Models\Work>}>,
     *   grades:\Illuminate\Support\Collection, // keyBy work_id
     *   subjectAverages:array<int,array{subject:string,average:?float}>,
     *   termLabels:array<int,string>,   // [trimester_id => '1er Trimestre' ...]
     *   termIds:array<int>,             // ids ordenados por starts_at
     *   termTable:array<string,array<int,?float>>, // ['Materia'=>[termId=>avg]]
     *   termPendings:array<int,int>,    // P por trimestre
     *   termZeros:array<int,int>,       // 0 sin P por trimestre
     *   allSubjects:array<int,string>,  // materias del alumno
     * }
     */
    public function build(int $studentId, ?int $weekId = null): array
    {
        /** @var Student $student */
        $student = Student::with(['group'])->findOrFail($studentId);

        /** @var Week|null $week */
        $week = $weekId
            ? Week::findOrFail($weekId)
            : Week::query()->orderBy('starts_at')->first();

        $currentTrimester = $week?->trimester;

        // Branding
        $settings = SchoolSetting::first();
        $logoUrl  = $settings?->logo_url ? asset('storage/'.$settings->logo_url) : null;

        // ------ Si no hay semana, regresamos estructura vacía pero con alumno/logo ------
        if (!$week) {
            return [
                'student'  => $student,
                'week'     => null,
                'currentTrimester' => null,
                'logoUrl'  => $logoUrl,

                'overallAvg' => null,
                'progressTotal' => 0,
                'progressDelivered' => 0,
                'progressPendingP' => 0,
                'progressNotDeliveredZero' => 0,
                'progressLabels' => [],

                'byDay'   => $this->emptyByDay(),
                'grades'  => collect(),
                'subjectAverages' => [],

                'termLabels' => [],
                'termIds'    => [],
                'termTable'  => [],
                'termPendings' => [],
                'termZeros'    => [],
                'allSubjects'  => [],
            ];
        }

        // ======== Trabajos de la semana del grupo del alumno ========
        $works = Work::query()
            ->where('week_id', $week->id)
            ->when($student->group_id, function ($q, $gid) {
                $q->whereHas('assignment', fn($qq) => $qq->where('group_id', $gid));
            })
            ->orderBy('weekday')->orderBy('name')
            ->get();

        $grades = GradeEntry::query()
            ->where('student_id', $student->id)
            ->whereIn('work_id', $works->pluck('id'))
            ->get()
            ->keyBy('work_id');

        // ======== Buckets por día ========
        $byDay = collect([
            'mon' => 'Lunes',
            'tue' => 'Martes',
            'wed' => 'Miércoles',
            'thu' => 'Jueves',
            'fri' => 'Viernes',
        ])->map(fn($l) => ['label'=>$l,'items'=>[]])->toArray();

        foreach ($works as $w) {
            $key = $w->weekday ?? 'mon';
            $byDay[$key]['items'][] = $w;
        }

        // ======== Resumen semanal (entregados / P / 0 sin P / promedio) ========
        $delivered = 0;           // J + normales con score no null
        $pendingP  = 0;           // status === 'P'
        $zerosNoP  = 0;           // score === 0.0 y status != 'P'
        $sum = 0.0; $count = 0;

        foreach ($works as $w) {
            /** @var GradeEntry|null $g */
            $g = $grades->get($w->id);
            $status = $g?->status;
            $score  = $g?->score;

            if ($status === 'P') {
                $pendingP++;
                // Para el promedio, P cuenta como 0
                $sum += 0; $count++;
            } elseif ($status === 'J') {
                $delivered++;
                $sum += 10; $count++;
            } else {
                if (!is_null($score)) {
                    $delivered++;
                    $sum += (float) $score; $count++;
                    if ((float) $score === 0.0) {
                        $zerosNoP++;
                    }
                }
                // score null y sin P => no lo metemos al promedio ni a zeros
            }
        }

        $overallAvg = $count > 0 ? round($sum / $count, 2) : null;

        // ======== Promedio por materia (semana) ========
        $bySubject = [];
        foreach ($works as $w) {
            $subjectName = optional($w->assignment?->subject)->name ?? 'Materia';
            $g = $grades->get($w->id);
            $status = $g?->status;
            $score  = $g?->score;

            if (!isset($bySubject[$subjectName])) {
                $bySubject[$subjectName] = ['sum'=>0.0,'count'=>0];
            }

            if ($status === 'P') {
                $bySubject[$subjectName]['sum']   += 0;
                $bySubject[$subjectName]['count'] += 1;
            } elseif ($status === 'J') {
                $bySubject[$subjectName]['sum']   += 10;
                $bySubject[$subjectName]['count'] += 1;
            } else {
                if (!is_null($score)) {
                    $bySubject[$subjectName]['sum']   += (float) $score;
                    $bySubject[$subjectName]['count'] += 1;
                }
            }
        }

        $subjectAverages = [];
        foreach ($bySubject as $subject => $st) {
            $avg = $st['count'] > 0 ? round($st['sum'] / $st['count'], 2) : null;
            $subjectAverages[] = ['subject'=>$subject,'average'=>$avg];
        }

        // ======== Materias del alumno (solo sus materias) ========
        $subjectNames = collect();
        $gid = $student->group_id;

        if ($gid) {
            $gst = GroupSubjectTeacher::with('subject')
                ->where('group_id', $gid)->get();
            foreach ($gst as $row) {
                $name = $row->subject?->name;
                if ($name) $subjectNames->push($name);
            }
        }
        // también incluir materias que aparezcan en la semana actual
        foreach ($works as $w) {
            $name = $w->assignment?->subject?->name;
            if ($name) $subjectNames->push($name);
        }

        $allSubjects = $subjectNames->filter()->unique()->sort()->values()->all();

        // ======== Trimestral: etiquetas, tabla, pendientes P, ceros sin P ========
        $termLabels = [];
        $termIds    = [];
        $termTable  = [];
        $termPendings = [];
        $termZeros    = [];

        $schoolYearId = $currentTrimester?->school_year_id;
        $trimesters = Trimester::query()
            ->when($schoolYearId, fn($q)=>$q->where('school_year_id', $schoolYearId))
            ->orderBy('starts_at')
            ->get();

        foreach ($trimesters as $idx => $t) {
            $termIds[] = $t->id;
            $termLabels[$t->id] = match ($idx) {
                0 => '1er Trimestre',
                1 => '2do Trimestre',
                default => '3er Trimestre',
            };
        }

        foreach ($allSubjects as $subjectName) {
            foreach ($trimesters as $t) {
                $weeksInT = Week::where('trimester_id', $t->id)->orderBy('starts_at')->get();

                $worksT = Work::query()
                    ->whereIn('week_id', $weeksInT->pluck('id'))
                    ->whereHas('assignment.subject', fn($q) => $q->where('name', $subjectName))
                    ->when($gid, fn($q,$groupId)=>$q->whereHas('assignment', fn($qq)=>$qq->where('group_id',$groupId)))
                    ->get();

                $gradesT = GradeEntry::query()
                    ->where('student_id', $student->id)
                    ->whereIn('work_id', $worksT->pluck('id'))
                    ->get()
                    ->keyBy('work_id');

                $sum = 0.0; $count = 0;
                $pend = $termPendings[$t->id] ?? 0;
                $zeros = $termZeros[$t->id] ?? 0;

                foreach ($worksT as $w) {
                    $g = $gradesT->get($w->id);
                    $status = $g?->status;
                    $score  = $g?->score;

                    if ($status === 'P') {
                        $pend++;
                        $sum += 0; $count++;
                    } elseif ($status === 'J') {
                        $sum += 10; $count++;
                    } else {
                        if (!is_null($score)) {
                            $sum += (float) $score; $count++;
                            if ((float) $score === 0.0) $zeros++;
                        }
                    }
                }

                $avg = $count > 0 ? round($sum / $count, 2) : null;
                $termTable[$subjectName][$t->id] = $avg;
                $termPendings[$t->id] = $pend;
                $termZeros[$t->id]    = $zeros;
            }
        }

        return [
            'student'  => $student,
            'week'     => $week,
            'currentTrimester' => $currentTrimester,
            'logoUrl'  => $logoUrl,

            'overallAvg' => $overallAvg,
            'progressTotal' => $works->count(),
            'progressDelivered' => $delivered,
            'progressPendingP'  => $pendingP,
            'progressNotDeliveredZero' => $zerosNoP,
            'progressLabels' => $works->pluck('name')->all(),

            'byDay'   => $byDay,
            'grades'  => $grades, // Collection keyBy work_id
            'subjectAverages' => $subjectAverages,

            'termLabels' => $termLabels,
            'termIds'    => $termIds,
            'termTable'  => $termTable,
            'termPendings' => $termPendings,
            'termZeros'    => $termZeros,
            'allSubjects'  => $allSubjects,
        ];
    }

    protected function emptyByDay(): array
    {
        return collect([
            'mon' => 'Lunes',
            'tue' => 'Martes',
            'wed' => 'Miércoles',
            'thu' => 'Jueves',
            'fri' => 'Viernes',
        ])->map(fn($l)=>['label'=>$l,'items'=>[]])->toArray();
    }
}
