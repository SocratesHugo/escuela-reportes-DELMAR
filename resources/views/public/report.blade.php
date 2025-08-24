@php
  // Variables que vienen del servicio:
  // $student, $week, $logoUrl, $overallAvg, $progressTotal, $progressDelivered, $progressPendingP, $progressNotDeliveredZero,
  // $byDay, $grades, $subjectAverages, $termLabels, $termTable, $termPendings, $termZeros, $allSubjects
@endphp
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte semanal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  {{-- Reusa tu CSS del panel --}}
  <link rel="stylesheet" href="{{ asset('css/filament/student-weekly-report.css') }}">
  <style>
    .container{max-width:1100px;margin:24px auto;padding:0 16px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px}
    .mt-4{margin-top:1rem}.mb-2{margin-bottom:.5rem}.mb-4{margin-bottom:1rem}
    .title{font-weight:800;font-size:20px;margin:0 0 6px;color:#0b2a4a}
    .btn{display:inline-block;padding:10px 16px;border-radius:10px;border:0;background:#0b2a4a;color:#fff;font-weight:700;cursor:pointer}
    .ok{background:#ecfdf5;border:1px solid #10b981;color:#065f46;padding:10px 12px;border-radius:10px;margin-bottom:12px}
    table{width:100%;border-collapse:separate;border-spacing:0}
    th{background:#f9fafb;color:#374151;font-weight:700;text-align:left;padding:.8rem 1rem;border-bottom:1px solid #e5e7eb}
    td{padding:.75rem 1rem;border-bottom:1px solid #e5e7eb;vertical-align:middle}
    .bg-h{background:#f8fafc}
    .day-header td{background:linear-gradient(90deg,#0b2a4a 0%,#143e6e 100%);color:#fff;font-weight:800}
    .is-p-row td{background:#fff7cc!important}
    .is-zero-row td{background:#ffe1e1!important}
  </style>
</head>
<body>
  <div class="container">
    @if(session('ok')) <div class="ok">{{ session('ok') }}</div> @endif

    {{-- Header --}}
    <div class="report-header">
      @if($logoUrl)<img class="logo" src="{{ $logoUrl }}" alt="Logo">@endif
      <div>
        <h1>Reporte Semanal del Estudiante</h1>
        <div class="sub">
          {{ $student?->full_name }} — Grupo: {{ $student?->group?->name ?? '—' }}
        </div>
      </div>
    </div>

    {{-- Resumen --}}
    <div class="card mb-4">
      <div class="title">Semana: {{ $week?->name ?? '—' }}</div>
      @if($week?->starts_at && $week?->ends_at)
        <div class="mb-2">{{ \Illuminate\Support\Carbon::parse($week->starts_at)->format('Y-m-d') }} a {{ \Illuminate\Support\Carbon::parse($week->ends_at)->format('Y-m-d') }}</div>
      @endif
      <div class="mb-2"><strong>Promedio general (semana):</strong> {{ is_null($overallAvg) ? '—' : number_format($overallAvg,2) }}</div>
      <div><strong>Progreso:</strong> {{ $progressDelivered }} / {{ $progressTotal }} — {{ $progressPendingP }} P — {{ $progressNotDeliveredZero }} no entregados</div>
    </div>

    {{-- Trabajos de la semana --}}
    <div class="card mb-4">
      <div class="title">Trabajos de la semana</div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Día</th><th>Trabajo</th><th>Materia</th><th>Estatus</th><th>Calificación</th><th>Comentarios</th>
            </tr>
          </thead>
          <tbody>
          @foreach ($byDay as $key => $bucket)
            @php $items = $bucket['items'] ?? []; @endphp
            <tr class="day-header"><td colspan="6">{{ $bucket['label'] ?? '' }}</td></tr>

            @if(empty($items))
              <tr class="bg-h"><td class="text-gray-500 italic" colspan="6">Sin trabajos registrados.</td></tr>
            @else
              @foreach ($items as $i => $w)
                @php
                  $g = $grades->get($w->id) ?? null;
                  $status = $g->status ?? null;
                  $score  = $g->score ?? null;

                  $rowClass = '';
                  if ($status === 'P') $rowClass = 'is-p-row';
                  elseif (is_null($status) && is_numeric($score) && (float)$score === 0.0) $rowClass = 'is-zero-row';
                @endphp
                <tr class="{{ $rowClass }}">
                  <td>{{ $bucket['label'] ?? '' }}</td>
                  <td>{{ $w->name }}</td>
                  <td>{{ optional($w->assignment?->subject)->name ?? '—' }}</td>
                  <td>
                    @if($status === 'P') P
                    @elseif($status === 'J') J
                    @else {{ is_null($score) ? 'Sin entregar' : 'normal' }}
                    @endif
                  </td>
                  <td>
                    @if($status === 'P') —
                    @else {{ is_null($score) ? '—' : number_format((float)$score, 2) }}
                    @endif
                  </td>
                  <td>{{ $g?->comment ?? '—' }}</td>
                </tr>
              @endforeach
            @endif
          @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Promedio por materia (semana) --}}
    <div class="card mb-4">
      <div class="title">Promedio por materia (semana)</div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Materia</th><th>Promedio</th></tr></thead>
          <tbody>
            @forelse ($subjectAverages as $row)
              <tr>
                <td>{{ $row['subject'] ?? '—' }}</td>
                <td>{{ array_key_exists('average',$row) && !is_null($row['average']) ? number_format($row['average'],2) : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-gray-500 italic">Sin calificaciones capturadas esta semana.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Promedio por materia (trimestre) --}}
    <div class="card mb-4">
      <div class="title">Promedio por materia (trimestre)</div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Materia</th>
              @foreach($termLabels as $tid => $label)
                <th class="text-center">{{ $label }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
          @foreach($allSubjects as $subject)
            <tr>
              <td><strong>{{ $subject }}</strong></td>
              @foreach($termLabels as $tid => $label)
                @php $val = $termTable[$subject][$tid] ?? null; @endphp
                <td class="text-center">{{ is_null($val) ? '—' : number_format($val,2) }}</td>
              @endforeach
            </tr>
          @endforeach
          <tr class="bg-h">
            <td><strong>Pendientes</strong></td>
            @foreach($termLabels as $tid => $label)
              <td class="text-center"><strong>{{ $termPendings[$tid] ?? 0 }}</strong></td>
            @endforeach
          </tr>
          <tr class="bg-h">
            <td><strong>Trabajos no entregados</strong></td>
            @foreach($termLabels as $tid => $label)
              <td class="text-center"><strong>{{ $termZeros[$tid] ?? 0 }}</strong></td>
            @endforeach
          </tr>
          </tbody>
        </table>
      </div>
    </div>

    {{-- Botón firmar --}}
    @isset($parent)
    <div class="card">
      <form method="post" action="{{ route('public.report.sign', [$parent->id, $student->id, $week->id]) }}">
        @csrf
        <button class="btn" type="submit">Firmar reporte</button>
      </form>
    </div>
    @endisset
  </div>
</body>
</html>
