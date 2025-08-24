<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Models\Week;
use App\Models\WeeklyReportSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicWeeklyReportController extends Controller
{
    /**
     * Muestra el reporte público de la semana para un alumno,
     * accesible con enlace firmado (parent/student/week).
     */
    public function show(Request $request, int $week, int $student, int $parent): View
    {
        // Carga básica
        $week   = Week::findOrFail($week);
        $student= Student::with('group')->findOrFail($student);
        $parent = User::findOrFail($parent); // asumiendo que los padres son Users

        // ¿Ya está firmado por este padre?
        $signature = WeeklyReportSignature::query()
            ->where('week_id', $week->id)
            ->where('student_id', $student->id)
            ->where('parent_id', $parent->id)
            ->first();

        // Si tienes un servicio que arma el “reporte del estudiante” reúsalo aquí.
        // Para no acoplar, mandamos lo mínimo indispensable a la vista pública.
        return view('public.weekly-report', [
            'week'       => $week,
            'student'    => $student,
            'parent'     => $parent,
            'signature'  => $signature,
            // Bandera: mostrar botón sólo a papás (este enlace SIEMPRE es de padre)
            'canSign'    => $signature === null, // si aún no firmó
        ]);
    }

    /**
     * Registra la firma. Idempotente.
     */
    public function sign(Request $request, int $week, int $student, int $parent)
    {
        $week   = Week::findOrFail($week);
        $student= Student::with('group')->findOrFail($student);
        $parent = User::findOrFail($parent);

        // Evita duplicados con transacción / firstOrCreate
        $sig = WeeklyReportSignature::firstOrCreate(
            [
                'week_id'    => $week->id,
                'student_id' => $student->id,
                'parent_id'  => $parent->id,
            ],
            [
                'parent_name'  => $parent->name ?? ($parent->paternal_lastname ?? '') . ' ' . ($parent->maternal_lastname ?? ''),
                'parent_email' => $parent->email,
                'signed_at'    => Carbon::now(),
            ]
        );

        // Si ya existía, asegúrate que tenga timestamp
        if (!$sig->signed_at) {
            $sig->signed_at = Carbon::now();
            $sig->save();
        }

        return back()->with('status', '¡Gracias! Tu firma quedó registrada.');
    }
}
