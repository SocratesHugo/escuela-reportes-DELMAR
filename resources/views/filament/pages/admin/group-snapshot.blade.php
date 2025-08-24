{{-- resources/views/filament/pages/admin/group-snapshot.blade.php --}}
<x-filament-panels::page>
    {{-- Encabezado --}}
    <x-filament::card>
        <div class="text-sm text-gray-700 space-y-1">
            <div>
                Grupo:
                <span class="font-medium">{{ $group?->name ?? '—' }}</span>
            </div>
            <div>
                Semana:
                <span class="font-medium">{{ $week?->name ?? '—' }}</span>
                @if($week?->starts_at && $week?->ends_at)
                    <span class="text-xs text-gray-500">
                        ({{ \Illuminate\Support\Carbon::parse($week->starts_at)->format('Y-m-d') }}
                        – {{ \Illuminate\Support\Carbon::parse($week->ends_at)->format('Y-m-d') }})
                    </span>
                @endif
            </div>

            <div class="pt-2 flex items-center gap-3">
                <a class="underline text-xs text-gray-600"
                   href="{{ \App\Filament\Pages\Admin\WeeklyDashboardPage::getUrl([
                        'weekId'  => (int) ($week->id ?? 0),
                        'groupId' => (int) ($group->id ?? 0),
                   ]) }}">
                    Volver al dashboard con este grupo
                </a>

                <x-filament::button
                    tag="a"
                    size="xs"
                    icon="heroicon-o-arrow-down-tray"
                    href="{{ route('admin.export.group-snapshot', ['groupId' => (int)($group?->id ?? 0), 'weekId' => (int)($week?->id ?? 0)]) }}"
                >
                    Exportar CSV (todas las materias)
                </x-filament::button>
            </div>
        </div>
    </x-filament::card>

    {{-- Materias --}}
    <div class="space-y-4 mt-4">
        @forelse($subjectsBoxes as $box)
            @php
                $rows = $box['rows'] ?? $box['works'] ?? [];
                $totDelivered=0; $totPending=0; $totZeros=0; $sum=0.0; $cnt=0;

                foreach ($rows as $r) {
                    $totDelivered += (int)($r['delivered'] ?? 0);
                    $totPending   += (int)($r['pending']   ?? 0);
                    $totZeros     += (int)($r['zeros']     ?? 0);
                    if (isset($r['avg']) && $r['avg']!=='') { $sum+=(float)$r['avg']; $cnt++; }
                }
                $computed = max(1,$totDelivered+$totPending+$totZeros);
                $pct_pending = $box['pct_pending'] ?? round(($totPending/$computed)*100,1);
                $avg_subject = $box['avg'] ?? ($cnt? round($sum/$cnt,2): null);
                $coverage    = $box['coverage'] ?? null;
                $gstId       = $box['gst_id'] ?? null;
            @endphp

            <x-filament::section :heading="$box['name'] ?? 'Materia'">
                {{-- Chips + export por materia (si hay gst_id) --}}
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 ring-1 ring-amber-200 px-2.5 py-1 text-xs font-medium">
                        <x-heroicon-o-clock class="w-4 h-4" />
                        Pendientes: <span class="font-semibold ml-1">{{ number_format($pct_pending,1) }}%</span>
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200 px-2.5 py-1 text-xs font-medium">
                        <x-heroicon-o-academic-cap class="w-4 h-4" />
                        Promedio: <span class="font-semibold ml-1">{{ is_null($avg_subject)? '—' : number_format($avg_subject,2) }}</span>
                    </span>
                    @if(!is_null($coverage))
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-200 px-2.5 py-1 text-xs font-medium">
                            <x-heroicon-o-chart-bar class="w-4 h-4" />
                            Cobertura: <span class="font-semibold ml-1">{{ number_format($coverage,1) }}%</span>
                        </span>
                    @endif

                    @if($gstId && $group?->id && $week?->id)
                        {{-- Si quieres exportar una sola materia, crea una ruta específica. Aquí lo dejamos como ejemplo de botón deshabilitado si no hay ruta --}}
                        <x-filament::button
                            size="xs"
                            color="gray"
                            icon="heroicon-o-arrow-down-tray"
                            disabled
                            title="(Opcional) Añade una ruta para exportar una sola materia con gst_id={{ $gstId }}"
                        >
                            Exportar CSV (esta materia)
                        </x-filament::button>
                    @endif
                </div>

                {{-- Tabla detalle --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Nombre</th>
                                <th class="px-3 py-2 text-center font-semibold">Entregados</th>
                                <th class="px-3 py-2 text-center font-semibold">Pendientes (P)</th>
                                <th class="px-3 py-2 text-center font-semibold">Sin entregar (0)</th>
                                <th class="px-3 py-2 text-center font-semibold">Promedio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['name'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-center">{{ (int)($row['delivered'] ?? 0) }}</td>
                                <td class="px-3 py-2 text-center text-amber-700 font-medium">{{ (int)($row['pending'] ?? 0) }}</td>
                                <td class="px-3 py-2 text-center text-rose-700 font-medium">{{ (int)($row['zeros'] ?? 0) }}</td>
                                <td class="px-3 py-2 text-center">
                                    {{ isset($row['avg']) && $row['avg']!=='' ? number_format((float)$row['avg'],2) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-3 text-sm text-gray-500 italic">Sin datos.</td>
                            </tr>
                        @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-3 py-2 text-right font-semibold">Totales</td>
                                <td class="px-3 py-2 text-center font-semibold">{{ $totDelivered }}</td>
                                <td class="px-3 py-2 text-center font-semibold">{{ $totPending }}</td>
                                <td class="px-3 py-2 text-center font-semibold">{{ $totZeros }}</td>
                                <td class="px-3 py-2 text-center font-semibold">
                                    {{ is_null($avg_subject)? '—' : number_format($avg_subject,2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        @empty
            <x-filament::card>
                <div class="text-sm text-gray-500 italic">No hay materias registradas para este grupo.</div>
            </x-filament::card>
        @endforelse
    </div>
</x-filament-panels::page>
