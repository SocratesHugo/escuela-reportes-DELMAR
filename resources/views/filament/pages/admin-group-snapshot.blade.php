{{-- resources/views/filament/pages/admin-group-snapshot.blade.php --}}
<x-filament::page>
    <div class="mb-3 text-sm text-gray-600">
        <div><strong>Grupo:</strong> {{ $group?->name ?? '—' }}</div>
        <div><strong>Semana:</strong> {{ $week?->label ?? ($week?->name ?? '—') }}</div>
    </div>

    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-filament::card>
            <div class="text-sm text-gray-500">Promedio del grupo</div>
            <div class="text-3xl font-bold mt-1">{{ is_null($metrics['avg']) ? '—' : number_format($metrics['avg'], 2) }}</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm text-gray-500">Entregados</div>
            <div class="text-3xl font-bold mt-1">{{ $metrics['delivered'] }}</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm text-gray-500">Pendientes (P)</div>
            <div class="text-3xl font-bold mt-1">{{ $metrics['pending'] }}</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm text-gray-500">Sin entregar</div>
            <div class="text-3xl font-bold mt-1">{{ $metrics['missing'] }}</div>
        </x-filament::card>
    </div>

    <x-filament::section :heading="'Alumnos × Materias'">
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-xs md:text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Alumno</th>
                        @foreach($subjects as $sid => $name)
                            <th class="px-3 py-2 text-center font-semibold">{{ $name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y">
                @forelse($students as $st)
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap">
                            {{ $st->paternal_lastname }} {{ $st->maternal_lastname }} {{ $st->names }}
                        </td>
                        @foreach($subjects as $sid => $name)
                            @php $cell = $table[$st->id][$sid] ?? null; @endphp
                            <td class="px-3 py-2 text-center">
                                @if($cell)
                                    <div>{{ $cell['delivered'] }}/{{ $cell['total'] }} entregados</div>
                                    <div class="text-amber-700">P: {{ $cell['pending'] }}</div>
                                    <div class="text-red-700">0/—: {{ $cell['missing'] }}</div>
                                    <a class="text-primary-600 underline text-xs" href="{{ $cell['link'] }}">ver detalle</a>
                                @else
                                    —
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="px-3 py-6 text-center text-gray-500" colspan="{{ 1 + count($subjects) }}">Sin alumnos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament::page>
