<?php

namespace App\Services;

use App\Models\GradeEntry;
use App\Models\Student;
use App\Models\SubjectWeeklyAverage;
use Illuminate\Support\Facades\DB;

class ComputeWeeklyAverages
{
    /**
     * Calcula y guarda promedios de una materia–grupo ($assignmentId) en una semana ($weekId).
     * Reglas: J=10, P=0, normal=score (0–10). Si no hay registro para un trabajo, cuenta como P (0).
     */
    public static function run(int $assignmentId, int $weekId): void
    {
        DB::transaction(function () use ($assignmentId, $weekId) {
            $workIds = DB::table('works')
                ->where('group_subject_teacher_id', $assignmentId)
                ->where('week_id', $weekId)
                ->pluck('id');

            if ($workIds->isEmpty()) {
                SubjectWeeklyAverage::where('group_subject_teacher_id', $assignmentId)
                    ->where('week_id', $weekId)
                    ->delete();
                return;
            }

            $groupId  = DB::table('group_subject_teacher')->where('id', $assignmentId)->value('group_id');
            $students = Student::where('group_id', $groupId)->pluck('id');

            $grades = GradeEntry::whereIn('work_id', $workIds)
                ->whereIn('student_id', $students)
                ->get()
                ->groupBy('student_id');

            foreach ($students as $studentId) {
                $rows = $grades->get($studentId, collect());

                $worksCount = $workIds->count();
                $pend = 0; $just = 0; $scored = 0;
                $sum  = 0.0;

                foreach ($workIds as $wid) {
                    $g = $rows->firstWhere('work_id', $wid);
                    if (!$g) {
                        $pend++;
                        continue;
                    }

                    if ($g->status === 'P') {
                        $pend++;
                    } elseif ($g->status === 'J') {
                        $just++;
                        $sum += 10.0;
                    } else {
                        $scored++;
                        $sum += (float) ($g->score ?? 0.0);
                    }
                }

                $avg = $worksCount > 0 ? round($sum / $worksCount, 2) : null;

                SubjectWeeklyAverage::updateOrCreate(
                    [
                        'student_id'               => (int) $studentId,
                        'group_subject_teacher_id' => (int) $assignmentId,
                        'week_id'                  => (int) $weekId,
                    ],
                    [
                        'avg'             => $avg,
                        'works_count'     => (int) $worksCount,
                        'scored_count'    => (int) $scored,
                        'pendings_count'  => (int) $pend,
                        'justified_count' => (int) $just,
                        'computed_at'     => now(),
                    ]
                );
            }
        });
    }
}
