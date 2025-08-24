<?php

namespace App\Http\Controllers\Exports;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GradeEntry;
use App\Models\Week;
use App\Models\Work;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuickCsvController extends Controller
{
    /**
     * CSV del resumen por grupo para una semana.
     * GET /admin/exports/weekly-group-summary/{weekId}
     */
    public function weeklyGroupSummary(int $weekId): StreamedResponse
    {
        $week = Week::findOrFail($weekId);

        $groups = Group::orderBy('name')->get();
        $filename = 'resumen_grupos_semana_' . $week->id . '.csv';

        return response()->streamDownload(function () use ($groups, $week) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Resumen por grupo — ' . $week->name]);
            fputcsv($out, []);
            fputcsv($out, ['Grupo', 'Promedio', 'Entregados', 'Pendientes (P)', 'Sin entregar (0)', 'Total capturas']);

            foreach ($groups as $group) {
                $workIds = Work::where('week_id', $week->id)
                    ->whereHas('groupSubjectTeacher', fn ($q) => $q->where('group_id', $group->id))
                    ->pluck('id');

                if ($workIds->isEmpty()) {
                    fputcsv($out, [$group->name, '', 0, 0, 0, 0]);
                    continue;
                }

                $studentIds = $group->students()->pluck('id');
                $grades = GradeEntry::whereIn('work_id', $workIds)
                    ->whereIn('student_id', $studentIds)->get();

                $del = 0; $pen = 0; $zer = 0; $sum=0.0; $cnt=0;
                foreach ($grades as $g) {
                    if ($g->status === 'P') { $pen++; $sum+=0.0; $cnt++; continue; }
                    if ($g->status === 'J') { $sum+=10.0; $cnt++; $del++; continue; }
                    if (is_numeric($g->score)) {
                        $score=(float)$g->score; $sum+=$score; $cnt++;
                        if ($score==0.0) $zer++; else $del++;
                    }
                }
                $avg = $cnt? round($sum/$cnt, 2): null;

                fputcsv($out, [
                    $group->name,
                    $avg,
                    $del,
                    $pen,
                    $zer,
                    $grades->count(),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * CSV para snapshot de grupo (todas las materias del grupo en la semana).
     * GET /admin/exports/group-snapshot/{groupId}/{weekId}
     */
    public function groupSnapshot(int $groupId, int $weekId): StreamedResponse
    {
        $group = Group::findOrFail($groupId);
        $week  = Week::findOrFail($weekId);

        $filename = 'snapshot_grupo_' . $group->name . '_semana_' . $week->id . '.csv';

        // Reutilizamos la lógica “simple”: sumamos por GST (materias)
        return response()->streamDownload(function () use ($group, $week) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Snapshot de grupo — ' . $group->name . ' — ' . $week->name]);
            fputcsv($out, []);
            fputcsv($out, ['Materia', 'Entregados', 'Pendientes (P)', 'Sin entregar (0)', 'Promedio']);

            $gsts = $group->groupSubjectTeachers()->with('subject')->get();
            foreach ($gsts as $gst) {
                $workIds = Work::where('week_id', $week->id)
                    ->where('group_subject_teacher_id', $gst->id)->pluck('id');

                if ($workIds->isEmpty()) {
                    continue;
                }

                $studentIds = $group->students()->pluck('id');
                $grades = GradeEntry::whereIn('work_id', $workIds)
                    ->whereIn('student_id', $studentIds)->get();

                $del=0; $pen=0; $zer=0; $sum=0.0; $cnt=0;
                foreach ($grades as $g) {
                    if ($g->status==='P') { $pen++; $sum+=0.0; $cnt++; continue; }
                    if ($g->status==='J') { $sum+=10.0; $cnt++; $del++; continue; }
                    if (is_numeric($g->score)) {
                        $score=(float)$g->score; $sum+=$score; $cnt++;
                        if ($score==0.0) $zer++; else $del++;
                    }
                }
                $avg = $cnt? round($sum/$cnt,2): null;

                fputcsv($out, [
                    $gst->subject?->name ?? 'Materia',
                    $del, $pen, $zer, $avg
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
