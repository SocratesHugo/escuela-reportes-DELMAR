<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Week;
use App\Models\WeeklyReportSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicReportSigningController extends Controller
{
    public function store(Request $request)
    {
        $data = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'week_id'    => 'required|exists:weeks,id',
            'parent_name'  => 'nullable|string|max:255',
            'parent_email' => 'nullable|email|max:255',
        ])->validate();

        $student = Student::findOrFail($data['student_id']);
        $week    = Week::findOrFail($data['week_id']);

        $signature = WeeklyReportSignature::updateOrCreate(
            [
                'student_id' => $student->id,
                'week_id'    => $week->id,
            ],
            [
                'parent_name'  => $data['parent_name']  ?? null,
                'parent_email' => $data['parent_email'] ?? null,
                'signed_at'    => now(),
                'ip'           => $request->ip(),
                'user_agent'   => substr($request->header('User-Agent', ''), 0, 512),
            ]
        );

        // Puedes redirigir a una página de “¡Gracias!” o volver al reporte
        return back()->with('status', '¡Firma registrada! Gracias.');
    }
}
