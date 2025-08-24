<?php

namespace App\Http\Controllers;

use App\Models\WeeklyReportSignature;
use App\Models\Student;
use App\Models\User;
use App\Models\Week;
use Illuminate\Http\Request;

class PublicReportController extends Controller
{
    /**
     * Guarda la firma de un papá/mamá para un reporte semanal.
     */
    public function sign(Request $request, int $parent, int $student, int $week)
    {
        $studentModel = Student::findOrFail($student);
        $weekModel    = Week::findOrFail($week);
        $parentModel  = User::findOrFail($parent);

        // Evitar duplicados (un padre no puede firmar el mismo reporte dos veces)
        $existing = WeeklyReportSignature::where('student_id', $studentModel->id)
            ->where('week_id', $weekModel->id)
            ->where('parent_email', $parentModel->email)
            ->first();

        if (!$existing) {
            WeeklyReportSignature::create([
                'student_id'   => $studentModel->id,
                'week_id'      => $weekModel->id,
                'parent_name'  => $parentModel->name,
                'parent_email' => $parentModel->email,
                'signed_at'    => now(),
                'ip'           => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);
        }

        return redirect()->back()->with('status', '¡Reporte firmado correctamente!');
    }
}
