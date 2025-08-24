{{-- resources/views/public/weekly-report.blade.php --}}
@extends('layouts.public')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    {{-- Encabezado --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">
            Reporte semanal — {{ $student->full_name ?? 'Alumno' }}
            <span class="block text-sm text-gray-500">
                {{ $week->label ?? '' }}
            </span>
        </h1>
    </div>

    {{-- Nomenclatura --}}
    <x-filament::card class="mb-4">
        <div class="text-sm">
            <div>J = Justificado (no cuenta)</div>
            <div>P = Pendiente (se cuenta como cero si no se regulariza)</div>
            <div>0 = No entregado</div>
            <div>1–10 = Calificación</div>
        </div>
    </x-filament::card>

    {{-- Leyenda visual --}}
    <div class="mt-2 mb-3 text-xs text-gray-600">
        <span class="inline-flex items-center mr-4">
            <span class="inline-block w-3 h-3 bg-yellow-50 border border-yellow-200 mr-1"></span> Pendiente (P)
        </span>
        <span class="inline-flex items-center">
            <span class="inline-block w-3 h-3 bg-red-50 border border-red-200 mr-1"></span> No entregado (0)
        </span>
    </div>

    {{-- Tabla --}}
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2 text-left">Día</th>
                    <th class="px-3 py-2 text-left">Trabajo</th>
                    <th class="px-3 py-2 text-left">Materia</th>
                    <th class="px-3 py-2 text-left">Estatus</th>
                    <th class="px-3 py-2 text-left">Calificación</th>
                    <th class="px-3 py-2 text-left">Comentarios</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    @include('reports.partials.row', ['row' => $row])
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-gray-500 italic">
                            Sin trabajos esta semana.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
