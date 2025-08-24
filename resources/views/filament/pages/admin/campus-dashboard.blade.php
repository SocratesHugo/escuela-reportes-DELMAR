{{-- resources/views/filament/pages/admin/campus-dashboard.blade.php --}}
<x-filament-panels::page>
    {{-- Filtros --}}
    <div class="mb-6">
        {{ $this->form }}
    </div>

    {{-- Contexto de semana --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::card>
            <div class="text-sm text-gray-500">Semana</div>
            <div class="mt-1 text-base font-semibold">
                @if($week)
                    {{ $week->name }}
                    <div class="text-xs text-gray-500">
                        {{ $week->starts_at }} – {{ $week->ends_at }}
                    </div>
                @else
                    —
                @endif
            </div>
        </x-filament::card>
    </div>

    {{-- Resumen por grupo --}}
    <x-filament::section :heading="'Resumen por grupo'">
        @if(empty($groupsRows))
            <div class="p-4 text-sm text-gray-500">
                No hay datos para la semana seleccionada.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Grupo</th>
                            <th class="px-3 py-2 text-center font-semibold">Promedio</th>
                            <th class="px-3 py-2 text-center font-semibold">Entregados</th>
                            <th class="px-3 py-2 text-center font-semibold">Pendientes (P)</th>
                            <th class="px-3 py-2 text-center font-semibold">Sin entregar (0)</th>
                            <th class="px-3 py-2 text-center font-semibold">Total capturas</th>
                            <th class="px-3 py-2 text-center font-semibold">Ver</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($groupsRows as $row)
                            <tr>
                                <td class="px-3 py-2 font-medium">
                                    {{ $row['group']->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    {{ is_null($row['avg'] ?? null) ? '—' : number_format($row['avg'], 2) }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    {{ $row['delivered'] ?? 0 }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    {{ $row['pending'] ?? 0 }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    {{ $row['missing'] ?? 0 }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    {{ $row['total'] ?? 0 }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if(!empty($row['link']))
                                        <a
                                            href="{{ $row['link'] }}"
                                            class="text-primary-600 hover:underline"
                                        >
                                            Snapshot de grupo
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
