<?php

namespace App\Services;

use App\Models\NotificationRule;
use App\Models\NotificationLog;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\ParentAlertMail;

class NotificationRuleEvaluator
{
    public function run(NotificationRule $rule): void
    {
        $trim = $rule->trimester ?? $this->detectCurrentTrimester();

        $students = Student::query()
            ->when($rule->school_year_id, fn($q) => $q->where('school_year_id', $rule->school_year_id))
            ->when($rule->group_id, fn($q) => $q->where('group_id', $rule->group_id))
            ->active()
            ->get();

        foreach ($students as $student) {
            [$pending, $missing, $subjectsList] = $this->countForStudent($student->id, $trim, $rule->subject_id);

            if ($pending >= $rule->threshold_pending || $missing >= $rule->threshold_missing) {
                $recentlyNotified = NotificationLog::where('notification_rule_id', $rule->id)
                    ->where('student_id', $student->id)
                    ->where('sent_at', '>=', now()->subDays($rule->cadence_days))
                    ->exists();

                if (!$recentlyNotified) {
                    $this->notifyParents($rule, $student, $trim, $pending, $missing, $subjectsList);

                    NotificationLog::create([
                        'notification_rule_id' => $rule->id,
                        'student_id'           => $student->id,
                        'snapshot'             => [
                            'trimester' => $trim,
                            'pending'   => $pending,
                            'missing'   => $missing,
                            'subjects'  => $subjectsList,
                        ],
                        'sent_at'              => now(),
                    ]);
                }
            }
        }

        $rule->update([
            'last_run_at' => now(),
            'next_run_at' => now()->addDays($rule->cadence_days),
        ]);
    }

    /** Simula sin enviar correos; retorna resumen y ejemplos. */
    public function simulate(NotificationRule $rule): array
    {
        $trim = $rule->trimester ?? $this->detectCurrentTrimester();

        $students = Student::query()
            ->when($rule->school_year_id, fn($q) => $q->where('school_year_id', $rule->school_year_id))
            ->when($rule->group_id, fn($q) => $q->where('group_id', $rule->group_id))
            ->active()
            ->get();

        $evaluated = 0;
        $matched   = 0;
        $examples  = [];

        foreach ($students as $student) {
            $evaluated++;

            [$pending, $missing, $subjectsList] = $this->countForStudent($student->id, $trim, $rule->subject_id);

            if ($pending >= $rule->threshold_pending || $missing >= $rule->threshold_missing) {
                $matched++;
                if (count($examples) < 5) {
                    $examples[] = [
                        'student'  => $student->full_name,
                        'pending'  => $pending,
                        'missing'  => $missing,
                        'subjects' => $subjectsList,
                    ];
                }
            }
        }

        return [
            'evaluated_count' => $evaluated,
            'matched_count'   => $matched,
            'examples'        => $examples,
        ];
    }

    protected function detectCurrentTrimester(): int
    {
        $m = (int) now()->format('n');
        if ($m >= 9 && $m <= 12) return 1; // Sep–Dic
        if ($m >= 1 && $m <= 3)  return 2; // Ene–Mar
        return 3;                          // Abr–Jun
    }

    /**
     * Cuenta por alumno en el trimestre:
     * - Pendientes (P) => grade_entries.status = 'P'
     * - Sin entregar   => grade_entries.score = 0 y status NOT IN ('P','J')
     * Filtra por materia si $onlySubjectId viene.
     */
    protected function countForStudent(int $studentId, int $trimester, ?int $onlySubjectId = null): array
    {
        $rows = DB::table('grade_entries as ge')
            ->join('works as w', 'w.id', '=', 'ge.work_id')
            ->join('weeks as wk', 'wk.id', '=', 'w.week_id')
            ->join('trimesters as tr', 'tr.id', '=', 'wk.trimester_id')
            ->leftJoin('group_subject_teacher as gst', 'gst.id', '=', 'w.group_subject_teacher_id')
            ->when($onlySubjectId, fn($q) => $q->where('gst.subject_id', $onlySubjectId))
            ->where('ge.student_id', $studentId)
            ->where('tr.number', $trimester)
            ->select([
                'ge.score',         // 0..10 o NULL
                'ge.status',        // 'normal','P','J'
                'gst.subject_id',   // para listar materias
            ])
            ->get();

        $pending    = 0; // P
        $missing    = 0; // 0 sin P/J
        $subjectIds = [];

        foreach ($rows as $r) {
            $status = $r->status;                   // 'normal' | 'P' | 'J'
            $score  = is_null($r->score) ? null : (int) $r->score;

            if ($status === 'P') {
                $pending++;
                if ($r->subject_id) $subjectIds[$r->subject_id] = true;
                continue;
            }

            if ($score === 0 && $status !== 'P' && $status !== 'J') {
                $missing++;
                if ($r->subject_id) $subjectIds[$r->subject_id] = true;
            }
        }

        $subjectsList = $this->subjectsNames(array_keys($subjectIds));

        return [$pending, $missing, $subjectsList];
    }

    protected function subjectsNames(array $ids): string
    {
        if (empty($ids)) return 'N/A';
        $names = DB::table('subjects')->whereIn('id', $ids)->pluck('name')->all();
        return implode(', ', $names);
    }

    protected function notifyParents(NotificationRule $rule, \App\Models\Student $student, int $trim, int $pending, int $missing, string $subjectsList): void
    {
        $vars = [
            '{student}'        => $student->full_name,
            '{group}'          => optional($student->group)->name ?? 'N/D',
            '{trimester}'      => "Trimestre {$trim}",
            '{pending_count}'  => (string) $pending,
            '{missing_count}'  => (string) $missing,
            '{subjects_list}'  => $subjectsList,
        ];

        $subject = strtr($rule->email_subject ?? 'Aviso de trabajos', $vars);
        $body    = strtr($rule->email_body ?? '', $vars);

        foreach ($student->parents as $parent) {
            if (!$parent->email) continue;
            Mail::to($parent->email)->queue(new ParentAlertMail($subject, $body));
        }
    }
}
