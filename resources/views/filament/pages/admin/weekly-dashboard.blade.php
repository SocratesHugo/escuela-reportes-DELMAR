{{-- resources/views/filament/pages/admin/weekly-dashboard.blade.php --}}
<x-filament-panels::page>
    {{-- Filtros --}}
    <div class="mb-6">
        {{ $this->form }}
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-filament::card>
            <div class="text-sm text-gray-500">Semana</div>
            <div class="mt-1">
                <div class="text-base font-semibold">
                    {{ $week?->name ?? '—' }}
                </div>
                @if($week?->starts_at && $week?->ends_at)
                    <div class="text-xs text-gray-500">
                        {{ \Illuminate\Support\Carbon::parse($week->starts_at)->format('Y-m-d') }}
                        – {{ \Illuminate\Support\Carbon::parse($week->ends_at)->format('Y-m-d') }}
                    </div>
                @endif
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Promedio general</div>
            <div class="mt-1 text-3xl font-bold">{{ is_null($kpiAvg) ? '—' : number_format($kpiAvg, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Promedio ponderado de la semana (J=10, P=0, 0=0).</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Cobertura de captura</div>
            <div class="mt-1 text-3xl font-bold">{{ number_format($kpiCoverage,1) }}%</div>
            <div class="text-xs text-gray-500 mt-1">Celdas con captura vs esperadas.</div>
        </x-filament::card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
        <x-filament::card>
            <div class="text-sm text-gray-500">Entregados</div>
            <div class="mt-1 text-3xl font-bold">{{ $kpiDelivered }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Pendientes (P)</div>
            <div class="mt-1 text-3xl font-bold">{{ $kpiPending }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Sin entregar (0)</div>
            <div class="mt-1 text-3xl font-bold">{{ $kpiZeros }}</div>
        </x-filament::card>
    </div>

    {{-- Export resumen por grupo --}}
    <div class="mt-6">
        <x-filament::button
            tag="a"
            href="{{ route('admin.export.weekly-group-summary', ['weekId' => (int)($week?->id ?? 0)]) }}"
            icon="heroicon-o-arrow-down-tray"
        >
            Exportar resumen por grupo (CSV)
        </x-filament::button>
    </div>

    {{-- Gráfica: Barras apiladas por grupo --}}
    <x-filament::card class="mt-4">
        <div class="text-base font-semibold mb-2">Mapa de estatus por grupo</div>
        <div id="stackedChart" class="min-h-[320px]"></div>
        <div class="text-xs text-gray-500 mt-2">
            Entregados (✔), Pendientes (P) y Sin entregar (0) – por grupo en la semana seleccionada.
        </div>
    </x-filament::card>

    {{-- Gráfica: Tendencia 6 semanas (promedio plantel) --}}
    <x-filament::card class="mt-4">
        <div class="text-base font-semibold mb-2">Tendencia de promedio (últimas 6 semanas)</div>
        <div id="trendChart" class="min-h-[320px]"></div>
    </x-filament::card>

    {{-- Tabla (quick view) que coincide con el CSV --}}
    <x-filament::card class="mt-4">
        <div class="text-base font-semibold mb-2">Resumen por grupo</div>
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($groupSummary as $row)
                    <tr>
                        <td class="px-3 py-2">{{ $row['group'] }}</td>
                        <td class="px-3 py-2 text-center">{{ is_null($row['avg']) ? '—' : number_format($row['avg'],2) }}</td>
                        <td class="px-3 py-2 text-center">{{ $row['delivered'] }}</td>
                        <td class="px-3 py-2 text-center">{{ $row['pending'] }}</td>
                        <td class="px-3 py-2 text-center">{{ $row['zeros'] }}</td>
                        <td class="px-3 py-2 text-center">{{ $row['captures'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-3 text-sm text-gray-500 italic">Sin datos.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::card>

    {{-- ApexCharts CDN + scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        (function () {
            // Data desde PHP
            const stackedLabels = @json($stacked['groups'] ?? []);
            const stackedDelivered = @json($stacked['series']['delivered'] ?? []);
            const stackedPending = @json($stacked['series']['pending'] ?? []);
            const stackedZeros = @json($stacked['series']['zeros'] ?? []);

            const trendLabels = @json($trend['labels'] ?? []);
            const trendValues = @json($trend['values'] ?? []);

            // Barras apiladas
            const stackedOptions = {
                chart: { type: 'bar', stacked: true, height: 320, toolbar: { show: false } },
                plotOptions: { bar: { horizontal: false } },
                series: [
                    { name: 'Entregados (✔)', data: stackedDelivered },
                    { name: 'Pendientes (P)', data: stackedPending },
                    { name: 'Sin entregar (0)', data: stackedZeros },
                ],
                xaxis: { categories: stackedLabels },
                legend: { position: 'top' },
            };
            new ApexCharts(document.querySelector('#stackedChart'), stackedOptions).render();

            // Tendencia 6 semanas
            const trendOptions = {
                chart: { type: 'line', height: 320, toolbar: { show: false } },
                stroke: { curve: 'smooth', width: 3 },
                series: [{ name: 'Promedio', data: trendValues }],
                xaxis: { categories: trendLabels },
                markers: { size: 3 },
                yaxis: { min: 0, max: 10 },
            };
            new ApexCharts(document.querySelector('#trendChart'), trendOptions).render();
        })();
    </script>
</x-filament-panels::page>
