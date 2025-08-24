{{-- resources/views/filament/pages/admin-weekly-dashboard.blade.php --}}
<x-filament::page>
    <div class="flex items-center gap-3 mb-4">
        <img src="{{ asset('images/logo-delmar.png') }}" class="h-8" onerror="this.style.display='none'">
        <h1 class="text-2xl font-bold text-slate-800">Dashboard semanal (admin)</h1>
    </div>

    {{-- Filtros --}}
    <div class="mb-6">
        {{ $this->form }}
    </div>

    @if(!$week)
        <div class="p-6 border rounded-xl bg-white text-sm text-slate-600">
            Selecciona una semana para ver el tablero.
        </div>
    @else
        {{-- Resumen de semana --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <x-filament::card>
                <div class="text-xs text-slate-500">Semana</div>
                <div class="text-sm font-medium text-slate-800">
                    {{ $week->name }}
                </div>
                @if($week?->starts_at && $week?->ends_at)
                    <div class="text-xs text-slate-500">
                        {{ \Illuminate\Support\Carbon::parse($week->starts_at)->format('Y-m-d') }}
                        a
                        {{ \Illuminate\Support\Carbon::parse($week->ends_at)->format('Y-m-d') }}
                    </div>
                @endif
            </x-filament::card>
            <x-filament::card>
                <div class="text-xs text-slate-500">Promedio general</div>
                <div class="text-3xl font-bold mt-1">{{ number_format($kpi_avg,2) }}</div>
            </x-filament::card>
            <x-filament::card>
                <div class="text-xs text-slate-500">Cobertura de captura</div>
                <div class="text-3xl font-bold mt-1">{{ number_format($kpi_coverage_pct,1) }}%</div>
                <div class="text-xs text-slate-500 mt-1">{{ $kpi_captured }} / {{ $kpi_expected }} celdas</div>
            </x-filament::card>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
            <x-filament::card>
                <div class="text-xs text-slate-500">Entregados</div>
                <div class="text-2xl font-semibold text-emerald-700">{{ $kpi_delivered }}</div>
            </x-filament::card>
            <x-filament::card>
                <div class="text-xs text-slate-500">Pendientes (P)</div>
                <div class="text-2xl font-semibold text-amber-600">{{ $kpi_pending }}</div>
            </x-filament::card>
            <x-filament::card>
                <div class="text-xs text-slate-500">Sin entregar (0)</div>
                <div class="text-2xl font-semibold text-rose-600">{{ $kpi_zero }}</div>
            </x-filament::card>
            <x-filament::card>
                <div class="text-xs text-slate-500">Firmas de padres</div>
                @if(!is_null($kpi_sign_pct))
                    <div class="text-2xl font-semibold text-sky-700">{{ number_format($kpi_sign_pct,1) }}%</div>
                @else
                    <div class="text-sm text-slate-400">—</div>
                    <div class="text-[11px] text-slate-400 mt-1">Agrega la tabla weekly_report_signatures para activar</div>
                @endif
            </x-filament::card>
        </div>

        {{-- Heatmap Grupo × Materia --}}
        <div class="border rounded-xl bg-white overflow-x-auto mb-6">
            <div class="px-4 py-2 bg-slate-50 border-b font-semibold text-slate-700">
                Mapa de pendientes por Grupo × Materia
            </div>
            <div class="p-3">
                @php
                    // columnas = materias únicas ordenadas
                    $subjects = [];
                    foreach ($heatmap as $g => $cols) { foreach($cols as $s => $v) $subjects[$s]=true; }
                    $subjects = array_keys($subjects);
                    sort($subjects);
                    $color = function($pct){
                        if ($pct >= 30) return 'bg-rose-100 text-rose-800';
                        if ($pct >= 15) return 'bg-amber-100 text-amber-800';
                        return 'bg-emerald-100 text-emerald-800';
                    };
                @endphp

                @if(empty($subjects))
                    <div class="text-sm text-slate-500">Sin datos para esta semana.</div>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-800 text-white">
                            <tr>
                                <th class="text-left py-2 px-3">Grupo \ Materia</th>
                                @foreach($subjects as $s)
                                    <th class="text-left py-2 px-3">{{ $s }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                        @foreach($heatmap as $g => $cols)
                            <tr class="odd:bg-white even:bg-slate-50">
                                <td class="py-2 px-3 font-medium">{{ $g }}</td>
                                @foreach($subjects as $s)
                                    @php
                                        $cell = $cols[$s] ?? null;
                                        $pct  = $cell['pending_pct'] ?? 0.0;
                                        $avg  = $cell['avg'] ?? 0.0;
                                    @endphp
                                    <td class="py-2 px-3">
                                        @if($cell)
                                            <div class="inline-flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded text-xs {{ $color($pct) }}">
                                                    {{ number_format($pct,1) }}% P
                                                </span>
                                                <span class="text-xs text-slate-500">Prom: {{ number_format($avg,2) }}</span>
                                            </div>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Top alertas --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-filament::card>
                <div class="text-sm font-semibold text-slate-800 mb-2">Grupos con más pendientes</div>
                <ul class="space-y-1 text-sm">
                    @forelse($top_groups_by_P as $row)
                        <li class="flex justify-between">
                            <span>{{ $row['group'] }}</span>
                            <span class="font-medium text-amber-700">{{ number_format($row['pending_pct'],1) }}%</span>
                        </li>
                    @empty
                        <li class="text-slate-400">Sin datos.</li>
                    @endforelse
                </ul>
            </x-filament::card>
            <x-filament::card>
                <div class="text-sm font-semibold text-slate-800 mb-2">Materias con más “0”</div>
                <ul class="space-y-1 text-sm">
                    @forelse($top_subjects_by_0 as $row)
                        <li class="flex justify-between">
                            <span>{{ $row['subject'] }}</span>
                            <span class="font-medium text-rose-700">{{ number_format($row['zero_pct'],1) }}%</span>
                        </li>
                    @empty
                        <li class="text-slate-400">Sin datos.</li>
                    @endforelse
                </ul>
            </x-filament::card>
            <x-filament::card>
                <div class="text-sm font-semibold text-slate-800 mb-2">Docentes con menor cobertura</div>
                <ul class="space-y-1 text-sm">
                    @forelse($top_teachers_low_coverage as $row)
                        <li class="flex justify-between">
                            <span>{{ $row['teacher'] }}</span>
                            <span class="font-medium text-slate-700">{{ number_format($row['coverage'],1) }}%</span>
                        </li>
                    @empty
                        <li class="text-slate-400">Sin datos.</li>
                    @endforelse
                </ul>
            </x-filament::card>
        </div>
    @endif
</x-filament::page>
