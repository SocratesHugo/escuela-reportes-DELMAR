@extends('layouts.app')

@section('content')
<div class="container mx-auto">
    {{-- ===================== CONTENIDO REAL DEL REPORTE ===================== --}}
    @include('reports.weekly', [
        'student' => $student,
        'week'    => $week,
    ])
    @if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 border border-green-200">
        {{ session('status') }}
    </div>
@endif
    {{-- =================== /CONTENIDO REAL DEL REPORTE ===================== --}}

    {{-- ===================== BOTÓN SOLO PARA PADRES ===================== --}}
    @if(isset($parent) && $parent)
        <div class="mt-6 text-center">
            <form method="POST" action="{{ route('public.report.sign', [
                'parent'  => $parent->id,
                'student' => $student->id,
                'week'    => $week->id,
            ]) }}">
                @csrf
                <button type="submit"
                        class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">
                    Firmar reporte
                </button>
            </form>
        </div>
    @endif
    {{-- =================== /BOTÓN SOLO PARA PADRES ===================== --}}
</div>
@endsection
