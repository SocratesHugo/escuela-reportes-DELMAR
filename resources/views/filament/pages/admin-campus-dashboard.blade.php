<x-filament::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-filament::card>
            <div class="text-sm text-gray-500">Promedio general (semana)</div>
            <div class="text-3xl font-bold mt-1">{{ is_null($avgScore) ? '—' : number_format($avgScore, 2) }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Entregados</div>
            <div class="text-3xl font-bold mt-1">{{ $delivered }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Pendientes (P)</div>
            <div class="text-3xl font-bold mt-1">{{ $pending }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Sin entregar (0 / vacíos)</div>
            <div class="text-3xl font-bold mt-1">{{ $missing }}</div>
        </x-filament::card>
    </div>

    <x-filament::section :heading="'Grupos — semana seleccionada'">
        @isset($week)
            <div class="mb-4 text-gray-600 text-sm">
                Semana:
                {{ \Carbon\Carbon::parse($week->starts_at)->format('Y-m-d') }}
                –
                {{ \Carbon\Carbon::parse($week->ends_at)->format('Y-m-d') }}
            </div>
        @endisset
        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Grupo</th>
                        <th class="px-4 py-3 text-center font-semibold">Promedio</th>
                        <th class="px-4 py-3 text-center font-semibold">Entregados</th>
                        <th class="px-4 py-3 text-center font-semibold">Pendientes</th>
                        <th class="px-4 py-3 text-center font-semibold">Sin entregar</th>
                        <th class="px-4 py-3 text-center font-semibold">Trabajos</th>
                        <th class="px-4 py-3 text-right font-semibold">Detalle</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($heatmapRows as $r)
                        <tr>
                            <td class="px-4 py-3">{{ $r['group'] }}</td>
                            <td class="px-4 py-3 text-center">{{ is_null($r['avg']) ? '—' : number_format($r['avg'], 2) }}</td>
                            <td class="px-4 py-3 text-center">{{ $r['delivered'] }}</td>
                            <td class="px-4 py-3 text-center">{{ $r['pending'] }}</td>
                            <td class="px-4 py-3 text-center">{{ $r['missing'] }}</td>
                            <td class="px-4 py-3 text-center">{{ $r['total'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <x-filament::button size="xs" tag="a" :href="$r['link']">Ver grupo</x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-gray-500" colspan="7">Sin datos para la semana.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament::page>
