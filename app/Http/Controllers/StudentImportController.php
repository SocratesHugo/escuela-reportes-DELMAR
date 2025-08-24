<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StudentsImport;

class StudentImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:xlsx,xls,csv','max:10240'], // 10MB
        ]);

        try {
            // Ejecuta con o sin colas según tu configuración
            Excel::import(new StudentsImport(auth()->user()), $request->file('file'));

            return back()->with('success', '¡Importación iniciada/completada! Revisa el resumen al final de la página.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Error al importar: '.$e->getMessage());
        }
    }
}
