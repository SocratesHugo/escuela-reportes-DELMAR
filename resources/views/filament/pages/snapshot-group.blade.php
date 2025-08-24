{{-- resources/views/filament/pages/snapshot-group.blade.php --}}
<x-filament::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    @if(!$group || !$week)
        <x-filament::section>
            <div class="text-sm text-gray-500">Selecciona grupo y semana.</div>
        </x-filament::section>
    @else
        <x-filament::section :heading="'Resumen — ' . $group->name">
            <div class="text-xs text-gray-500 mb-3">
                Semana: {{ $week->name }}
                @if($week->starts_at && $week->ends_at)
                    — {{ \Illuminate\Support\Carbon::parse($week->starts_at)->format('Y-m-d') }}
                    a {{ \Illuminate\Support\Carbon::parse($week->ends_at)->format('Y-m-d') }}
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="bg-slate-50">
                        <th class="text-left px-3 py-2">Alumno</th>
                        <th class="text-center px-3 py-2">Entregados</th>
                        <th class="text-center px-3 py-2">Pendientes</th>
                        <th class="text-center px-3 py-2">Sin entregar</th>
                        <th class="text-center px-3 py-2">Progreso</th>
                        <th class="text-center px-3 py-2">Promedio</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr class="border-b">
                            <td class="px-3 py-2">
                                {{ $r['student']->paternal_lastname }} {{ $r['student']->maternal_lastname }}, {{ $r['student']->names }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded bg-green-100 text-green-800">{{ $r['delivered'] }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">{{ $r['pending'] }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-800">{{ $r['missing'] }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                {{ $r['delivered'] }} / {{ $r['deliverable'] }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                {{ is_null($r['avg']) ? '—' : number_format($r['avg'],2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-gray-500">Sin alumnos.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament::page>
