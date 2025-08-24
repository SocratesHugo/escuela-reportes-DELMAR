{{-- resources/views/filament/pages/admin-group-subject-snapshot.blade.php --}}
<x-filament::page>
    <div class="mb-2 text-sm text-gray-600">
        <div><strong>Grupo:</strong> {{ $group?->name ?? '—' }}</div>
        <div><strong>Materia:</strong> {{ $subjectName ?? '—' }}</div>
        <div><strong>Semana:</strong> {{ $week?->label ?? ($week?->name ?? '—') }}</div>
    </div>

    <x-filament::section :heading="'Alumnos — KPIs de la materia en la semana'">
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Alumno</th>
                        <th class="px-4 py-3 text-center font-semibold">Entregados</th>
                        <th class="px-4 py-3 text-center font-semibold">Pendientes (P)</th>
                        <th class="px-4 py-3 text-center font-semibold">Sin entregar</th>
                        <th class="px-4 py-3 text-center font-semibold">Trabajos</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $r)
                        <tr>
                            <td class="px-4 py-2">{{ $r['student_name'] }}</td>
                            <td class="px-4 py-2 text-center">{{ $r['delivered'] }}</td>
                            <td class="px-4 py-2 text-center text-amber-700">{{ $r['pending'] }}</td>
                            <td class="px-4 py-2 text-center text-red-700">{{ $r['missing'] }}</td>
                            <td class="px-4 py-2 text-center">{{ $r['total'] }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-gray-500" colspan="5">Sin registros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament::page>
