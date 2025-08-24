<?php

namespace App\Exports;

use App\Models\GradeEntry;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use App\Models\Week;
use App\Models\Work;
use App\Support\Grades;
use App\Support\TermResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TeacherGradesDetailSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        public \App\Models\User $teacher,
        public ?int $groupId = null,
        public ?int $gstId = null,
        public ?int $weekFromId = null,
        public ?int $weekToId = null,
    ) {}

    public function title(): string
    {
        return 'Detalle';
    }

    public function headings(): array
    {
        return [
            'Grupo',
            'Alumno',
            'Materia',
            'Trimestre',
            'Semana',
            'Fechas semana',
            'Día',
            'Trabajo',
            'Estatus',
            'Calificación',
            'Calificación efectiva',
            'Comentario',
        ];
    }

    public function collection()
    {
        $gstQuery = GroupSubjectTeacher::query()
            ->where('teacher_id', $this->teacher->id);

        if ($this->groupId) $gstQuery->where('group_id', $this->groupId);
        if ($this->gstId)   $gstQuery->where('id', $this->gstId);

        $gstAll = $gstQuery->with(['group:id,name','subject:id,name'])->get();
        if ($gstAll->isEmpty()) return collect();

        $weeksQ = Week::query();
        if ($this->weekFromId) $weeksQ->where('id', '>=', $this->weekFromId);
        if ($this->weekToId)   $weeksQ->where('id', '<=', $this->weekToId);
        $weeks = $weeksQ->get()->keyBy('id');

        $worksQ = Work::query()->whereIn('group_subject_teacher_id', $gstAll->pluck('id'));
        if ($this->weekFromId || $this->weekToId) {
            $worksQ->whereIn('week_id', $weeks->keys());
        }
        $works = $worksQ->with('assignment.subject')->get()->groupBy('group_subject_teacher_id');

        $weekdayText = fn ($w) => match(strtolower((string)$w)) {
            'mon','1' => 'Lunes',
            'tue','2' => 'Martes',
            'wed','3' => 'Miércoles',
            'thu','4' => 'Jueves',
            'fri','5' => 'Viernes',
            default   => ucfirst((string)$w),
        };

        $rows = collect();

        foreach ($gstAll as $gst) {
            $groupName   = $gst->group?->name ?? '—';
            $subjectName = $gst->subject?->name ?? 'Materia';

            $students = Student::where('group_id', $gst->group_id)
                ->orderBy('paternal_lastname')->orderBy('maternal_lastname')->orderBy('names')->get();

            $gstWorks = $works->get($gst->id) ?? collect();

            foreach ($students as $s) {
                $grades = GradeEntry::where('student_id', $s->id)
                    ->whereIn('work_id', $gstWorks->pluck('id'))
                    ->get()->keyBy('work_id');

                foreach ($gstWorks as $w) {
                    $wk = $weeks->get($w->week_id) ?? Week::find($w->week_id);
                    if (!$wk) continue;

                    $term = TermResolver::forWeek($wk);
                    $g    = $grades->get($w->id);

                    $score  = $g->score  ?? null;
                    $status = $g->status ?? null;
                    [$label] = Grades::badge($score, $status);

                    $eff = Grades::effectiveScore($score, $status);

                    $range = '';
                    if ($wk->starts_at && $wk->ends_at) {
                        $range = Carbon::parse($wk->starts_at)->format('Y-m-d') . ' a ' . Carbon::parse($wk->ends_at)->format('Y-m-d');
                    }

                    $rows->push([
                        $groupName,
                        trim("{$s->paternal_lastname} {$s->maternal_lastname}, {$s->names}"),
                        $subjectName,
                        $term,
                        $wk->name ?? 'Semana',
                        $range,
                        $weekdayText($w->weekday),
                        $w->name ?? 'Trabajo',
                        $label,
                        is_null($score) ? null : (float)$score,
                        $eff,
                        $g?->comment,
                    ]);
                }
            }
        }

        return $rows;
    }
}
