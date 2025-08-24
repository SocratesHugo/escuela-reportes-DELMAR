{{-- resources/views/filament/pages/student-weekly-report-admin.blade.php --}}
<x-filament-panels::page>
    <style>:root{ --report-title-size: clamp(28px, 2.8vw, 40px); }</style>
    <link rel="stylesheet" href="{{ asset('css/filament/student-weekly-report.css') }}">

    @php
        /** @var \App\Models\Student|null $student */
        /** @var \App\Models\Week|null $week */
        $student = $student ?? ($this->student ?? null);
        $week    = $week    ?? ($this->week    ?? null);

        // Semana: "Semana X YYYY-MM-DD a YYYY-MM-DD"
        $weekLabel = '—';
        if ($week?->name) {
            $start = $week?->starts_at ? \Illuminate\Support\Carbon::parse($week->starts_at)->format('Y-m-d') : null;
            $end   = $week?->ends_at   ? \Illuminate\Support\Carbon::parse($week->ends_at)->format('Y-m-d')   : null;
            $weekLabel = trim($week->name . ' ' . ($start && $end ? "{$start} a {$end}" : ''));
        }

        // KPIs defensivos
        $overallAvg        = $overallAvg        ?? ($this->overallAvg        ?? null);
        $progressDelivered = $progressDelivered ?? ($this->progressDelivered ?? 0);
        $progressTotal     = $progressTotal     ?? ($this->progressTotal     ?? 0);
        $progressPending   = $progressPending   ?? ($this->progressPending   ?? 0);

        $byDay             = $byDay             ?? ($this->byDay ?? []);
        $grades            = $grades            ?? ($this->grades ?? collect());

        $termLabels        = $termLabels        ?? ($this->termLabels ?? []);
        $allSubjects       = $allSubjects       ?? ($this->allSubjects ?? []);
        $termTable         = $termTable         ?? ($this->termTable ?? []);
        $termPendings      = $termPendings      ?? ($this->termPendings ?? []);
        $termZeros         = $termZeros         ?? ($this->termZeros ?? []);
        $pendingSummary    = $pendingSummary    ?? ($this->pendingSummary ?? []);
        $termPWorks        = $termPWorks        ?? ($this->termPWorks ?? []);
        $termZeroWorks     = $termZeroWorks     ?? ($this->termZeroWorks ?? []);
    @endphp

    {{-- Encabezado --}}
    <div class="report-header">
        <img class="logo h-16 md:h-20 w-auto" src="{{ asset('images/logo-delmar.png') }}" alt="DELMAR"
             srcset="{{ asset('images/logo-delmar.png') }} 1x, {{ asset('images/logo-delmar@2x.png') }} 2x" />
        <div class="report-header-text">
            <h1>Reporte Semanal del Estudiante</h1>
            <p class="sub">
                {{ $student?->full_name }}
                @if($student?->group?->name) — Grupo: {{ $student->group->name }} @endif
                <br>
                <span class="text-gray-500">Semana: {{ $weekLabel }}</span>
            </p>
        </div>
    </div>

    @if($student && $week)
        {{-- Semana seleccionada --}}
        <x-filament::section :heading="'Semana seleccionada'">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-filament::card>
                    <div class="text-sm text-gray-500">Promedio general (semana)</div>
                    <div class="text-3xl font-bold mt-1">
                        {{ is_null($overallAvg) ? '—' : number_format((float)$overallAvg, 2) }}
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="text-sm text-gray-500">Trabajos entregados vs totales (semana)</div>
                    <div class="text-xl font-semibold mt-1">
                        {{ $progressDelivered }} / {{ $progressTotal }}
                        — <span class="text-amber-600 font-medium">{{ $progressPending }}</span> pendientes
                    </div>
                </x-filament::card>

                <x-filament::card>
                    <div class="text-sm text-gray-500">Semana</div>
                    <div class="text-sm md:text-base font-semibold mt-1">{{ $weekLabel }}</div>
                </x-filament::card>
            </div>
        </x-filament::section>

        {{-- Nomenclatura --}}
        <x-filament::section :heading="'Nomenclatura'">
            <div class="p-3 text-sm bg-white border rounded-xl shadow-sm">
                <ul class="space-y-1 text-slate-700">
                    <li><span class="font-semibold">J</span> = Trabajo Justificado <span class="text-slate-500">(no se tiene que hacer)</span></li>
                    <li><span class="font-semibold">P</span> = Trabajo Pendiente <span class="text-slate-500">(se cuenta como cero)</span></li>
                    <li><span class="font-semibold">0</span> = Sin entregar</li>
                    <li><span class="font-semibold">1–10</span> = Calificación del trabajo</li>
                </ul>
            </div>
        </x-filament::section>

        {{-- Trabajos de la semana --}}
        <x-filament::section :heading="'Trabajos de la semana'">
            <div class="overflow-x-auto bg-white border rounded-xl shadow-sm">
                <table class="min-w-full text-sm student-weekly-table">
                    <thead>
                    <tr class="bg-gray-50">
                        <th class="th">Día</th>
                        <th class="th">Trabajo</th>
                        <th class="th">Materia</th>
                        <th class="th">Estatus</th>
                        <th class="th">Calificación</th>
                        <th class="th">Comentarios</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($byDay as $key => $group)
                        <tr class="day-header">
                            <td class="td font-semibold text-white" colspan="6">{{ $group['label'] ?? ucfirst($key) }}</td>
                        </tr>

                        @php $items = $group['items'] ?? []; @endphp
                        @if(empty($items))
                            <tr class="zebra"><td class="td text-gray-500 italic" colspan="6">Sin trabajos registrados.</td></tr>
                        @else
                            @foreach($items as $wItem)
                                @php
                                    $g = is_array($grades) ? ($grades[$wItem->id] ?? null) : $grades->get($wItem->id ?? null);
                                    $status = $g->status ?? null; $score = $g->score ?? null;

                                    $rowClass   = 'zebra';
                                    $isPending  = ($status === 'P');
                                    $isZeroNoP  = (is_null($status) || ($status !== 'P' && $status !== 'J'))
                                                  && (is_null($score) || (is_numeric($score) && (float)$score === 0.0));
                                    if ($isPending)     $rowClass .= ' is-p-row';
                                    elseif ($isZeroNoP) $rowClass .= ' is-zero-row';

                                    if     ($status === 'P') { $statusLabel = 'P'; $badgeClass = 'badge badge-p'; }
                                    elseif ($status === 'J') { $statusLabel = 'J'; $badgeClass = 'badge badge-j'; }
                                    else {
                                        if ($isZeroNoP)                                  { $statusLabel = 'Sin entregar'; $badgeClass = 'badge badge-zero'; }
                                        elseif (!is_null($score) && (float)$score >= 1.0){ $statusLabel = 'Entregado';    $badgeClass = 'badge badge-ok'; }
                                        else                                            { $statusLabel = 'normal';       $badgeClass = 'badge badge-normal'; }
                                    }

                                    $scoreLabel  = ($status === 'P') ? '—' : (is_null($score) ? '—' : number_format((float)$score, 2));
                                    $subjectName = optional($wItem->assignment?->subject)->name ?? '—';
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="td">{{ $group['label'] ?? ucfirst($key) }}</td>
                                    <td class="td">{{ $wItem->name }}</td>
                                    <td class="td">{{ $subjectName }}</td>
                                    <td class="td"><span class="{{ $badgeClass }}">{{ $statusLabel }}</span></td>
                                    <td class="td">{{ $scoreLabel }}</td>
                                    <td class="td">{{ $g?->comment ?? '—' }}</td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Promedio trimestral de trabajos --}}
        <x-filament::section :heading="'Promedio trimestral de trabajos'">
            <div class="overflow-x-auto bg-white border rounded-xl shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="th text-left">Materia</th>
                        @foreach(($termLabels ?? []) as $tid => $label)
                            <th class="th text-center">{{ $label }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse(($allSubjects ?? []) as $subject)
                        <tr>
                            <td class="td font-medium">{{ $subject }}</td>
                            @foreach(($termLabels ?? []) as $tid => $label)
                                @php $val = $termTable[$subject][$tid] ?? null; @endphp
                                <td class="td text-center">{{ is_null($val) ? '—' : number_format((float)$val, 2) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="td text-gray-500 italic" colspan="{{ 1 + count($termLabels ?? []) }}">Sin materias.</td></tr>
                    @endforelse
                    <tr class="bg-gray-50">
                        <td class="td font-semibold">Pendientes</td>
                        @foreach(($termLabels ?? []) as $tid => $label)
                            <td class="td text-center font-semibold">{{ $termPendings[$tid] ?? 0 }}</td>
                        @endforeach
                    </tr>
                    <tr class="bg-gray-50">
                        <td class="td font-semibold">Trabajos no entregados</td>
                        @foreach(($termLabels ?? []) as $tid => $label)
                            <td class="td text-center font-semibold">{{ $termZeros[$tid] ?? 0 }}</td>
                        @endforeach
                    </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Trabajos pendientes por trimestre --}}
        <x-filament::section :heading="'Trabajos pendientes por trimestre'">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($termLabels as $tid => $label)
                    @php
                        $pList = $termPWorks[$tid] ?? [];
                        $zList = $termZeroWorks[$tid] ?? [];
                    @endphp
                    <div class="p-4 bg-white rounded-xl shadow-sm border">
                        <h3 class="text-xl font-extrabold text-[#0b2a4a] mb-3">{{ $label }}</h3>

                        <div class="space-y-6">
                            <div>
                                <div class="text-sm text-gray-600 mb-2">
                                    Pendientes: <span class="font-semibold">{{ $pendingSummary[$tid]['withP'] ?? 0 }}</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm border rounded-md">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold">Trabajo</th>
                                            <th class="px-3 py-2 text-left font-semibold">Semana</th>
                                            <th class="px-3 py-2 text-left font-semibold">Fechas</th>
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                        @forelse($pList as $row)
                                            @php
                                                $start = $row['week_starts_at'] ?? null;
                                                $end   = $row['week_ends_at']   ?? null;
                                                if ($start instanceof \Illuminate\Support\Carbon || $start instanceof \Carbon\Carbon) $start = $start->format('Y-m-d');
                                                if ($end   instanceof \Illuminate\Support\Carbon || $end   instanceof \Carbon\Carbon)   $end   = $end->format('Y-m-d');
                                            @endphp
                                            <tr>
                                                <td class="px-3 py-2">{{ $row['name'] }}</td>
                                                <td class="px-3 py-2">{{ $row['week'] ?? '—' }}</td>
                                                <td class="px-3 py-2">@if($start && $end) {{ $start }} – {{ $end }} @else — @endif</td>
                                            </tr>
                                        @empty
                                            <tr><td class="px-3 py-2 text-gray-500 italic" colspan="3">—</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div>
                                <div class="text-sm text-gray-600 mb-2">
                                    No entregados:
                                    <span class="font-semibold text-red-600">{{ $pendingSummary[$tid]['withoutP'] ?? 0 }}</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm border rounded-md">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold">Trabajo</th>
                                            <th class="px-3 py-2 text-left font-semibold">Semana</th>
                                            <th class="px-3 py-2 text-left font-semibold">Fechas</th>
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                        @forelse($zList as $row)
                                            @php
                                                $start = $row['week_starts_at'] ?? null;
                                                $end   = $row['week_ends_at']   ?? null;
                                                if ($start instanceof \Illuminate\Support\Carbon || $start instanceof \Carbon\Carbon) $start = $start->format('Y-m-d');
                                                if ($end   instanceof \Illuminate\Support\Carbon || $end   instanceof \Carbon\Carbon)   $end   = $end->format('Y-m-d');
                                            @endphp
                                            <tr>
                                                <td class="px-3 py-2">{{ $row['name'] }}</td>
                                                <td class="px-3 py-2">{{ $row['week'] ?? '—' }}</td>
                                                <td class="px-3 py-2">@if($start && $end) {{ $start }} – {{ $end }} @else — @endif</td>
                                            </tr>
                                        @empty
                                            <tr><td class="px-3 py-2 text-gray-500 italic" colspan="3">—</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @else
        <div class="text-sm text-gray-600">
            Selecciona un <span class="font-medium">alumno</span> y una <span class="font-medium">semana</span> para ver el reporte.
        </div>
    @endif
</x-filament-panels::page>
