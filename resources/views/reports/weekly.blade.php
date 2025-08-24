{{-- resources/views/reports/weekly.blade.php --}}
@php
    /**
     * Variables esperadas:
     * - $student: App\Models\Student
     * - $week: array|object con al menos name y, de preferencia, starts_at / ends_at
     * - $branding (opcional): ['school_name' => 'Mi Escuela', 'logo_url' => '/storage/branding/logo.png', 'primary_color' => '#0ea5e9']
     * - $summary (opcional): ['avg' => 8.6, 'completed' => 12, 'pending' => 2]
     * - $grades: lista de trabajos evaluados de la semana:
     *     ['subject' => 'Matemáticas', 'work' => 'Tarea 3', 'date' => '2025-08-08', 'score' => 9.5, 'max' => 10, 'weight' => 20, 'comment' => 'Buen proceso']
     * - $attendance (opcional): ['present' => 23, 'absent' => 1, 'late' => 0, 'justified' => 1]
     * - $teacherNotes (opcional): array de strings
     */

    use Illuminate\Support\Str;
    use Illuminate\Support\Carbon;

    $brand = $branding ?? [
        'school_name'   => config('app.name', 'Colegio'),
        'logo_url'      => null,
        'primary_color' => '#2563eb',
    ];

    // Helper seguro para obtener valores desde array u objeto:
    $w = $week ?? null;
    $wName  = is_array($w) ? ($w['name'] ?? null) : ($w->name ?? null);
    $wStart = is_array($w) ? ($w['starts_at'] ?? null) : ($w->starts_at ?? null);
    $wEnd   = is_array($w) ? ($w['ends_at']   ?? null) : ($w->ends_at   ?? null);

    // Normalizar a 'Y-m-d' si existen:
    $fmt = fn($d) => $d
        ? ( $d instanceof Carbon ? $d->format('Y-m-d') : Carbon::parse($d)->format('Y-m-d') )
        : null;

    $wStartStr = $fmt($wStart);
    $wEndStr   = $fmt($wEnd);

    // Etiqueta compacta: "Semana X 2025-09-01 a 2025-09-05"
    $weekCompact = $wName
        ? trim($wName.' '.(($wStartStr && $wEndStr) ? "{$wStartStr} a {$wEndStr}" : ''))
        : '—';
@endphp
<!DOCTYPE html>
<html lang="es" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte semanal — {{ $student->paternal_lastname }} {{ $student->maternal_lastname }}, {{ $student->names }}</title>

    <style>
        :root{
            --brand: {{ $brand['primary_color'] }};
            --ink: #0f172a;
            --muted: #475569;
            --bg: #ffffff;
            --soft: #f8fafc;
            --ok: #16a34a;
            --warn: #ea580c;
            --bad: #dc2626;
            --card: #ffffff;
            --border: #e2e8f0;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:var(--bg);color:var(--ink);font-family: ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,"Noto Sans",sans-serif;line-height:1.45}

        .container{max-width: 1040px;margin: 0 auto;padding: 24px}
        header{
            display:flex;align-items:center;gap:16px;border-bottom:1px solid var(--border);padding-bottom:16px;margin-bottom:24px
        }
        header .logo{
            width:80px;height:80px;border-radius:12px;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center
        }
        header .logo img{width:100%;height:100%;object-fit:contain}
        header .title{flex:1}
        header h1{margin:0;font-size:24px}
        header .sub{color:var(--muted);font-size:14px;margin-top:2px}
        .toolbar{display:flex;gap:8px;flex-wrap:wrap}
        .btn{
            appearance:none;border:1px solid var(--border);background:var(--card);padding:8px 12px;border-radius:10px;
            cursor:pointer;font-size:14px
        }
        .btn-primary{border-color:var(--brand);background:var(--brand);color:white}
        .btn:focus{outline:2px solid var(--brand);outline-offset:2px}

        .grid{display:grid;gap:16px}
        .grid-3{grid-template-columns: repeat(3, minmax(0, 1fr))}
        .grid-2{grid-template-columns: repeat(2, minmax(0, 1fr))}
        @media (max-width: 820px){
            .grid-3{grid-template-columns: 1fr}
            .grid-2{grid-template-columns: 1fr}
        }

        .card{
            background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px
        }
        .card h3{margin:0 0 8px 0;font-size:16px}
        .muted{color:var(--muted)}
        .kpi{display:flex;align-items:baseline;gap:8px}
        .kpi .big{font-size:28px;font-weight:700}
        .kpi .unit{font-size:12px;color:var(--muted)}

        table{width:100%;border-collapse:collapse}
        th, td{padding:10px 12px;border-bottom:1px solid var(--border);text-align:left;font-size:14px}
        thead th{background:var(--soft);font-weight:600}
        tbody tr:hover{background:#f9fafb}
        .score{font-weight:600}
        .score.ok{color:var(--ok)}
        .score.warn{color:var(--warn)}
        .score.bad{color:var(--bad)}

        .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:var(--soft);border:1px solid var(--border);font-size:12px;color:var(--muted)}
        .subject{font-weight:600}

        footer{margin-top:28px;padding-top:16px;border-top:1px solid var(--border);color:var(--muted);font-size:12px}

        /* Impresión A4 */
        @page{size:A4;margin:14mm}
        @media print{
            .no-print{display:none !important}
            body{background:white}
            header{border-bottom:none;margin-bottom:12px}
            .container{padding:0}
            .card{border:1px solid #ddd}
            a[href]:after{content:""}
        }
    </style>
</head>
<body>

<div class="container">
    {{-- ENCABEZADO --}}
    <header>
        <div class="logo">
            @if($brand['logo_url'])
                <img src="{{ $brand['logo_url'] }}" alt="Logo">
            @else
                {{-- fallback: iniciales --}}
                <span style="font-weight:700;color:var(--brand);font-size:18px;">
                    {{ Str::of($brand['school_name'] ?? 'Colegio')->trim()->substr(0,2)->upper() }}
                </span>
            @endif
        </div>
        <div class="title">
            <h1>{{ $brand['school_name'] ?? 'Colegio' }}</h1>
            <div class="sub">
                {{-- Semana compacta usando starts_at/ends_at de BD --}}
                Reporte semanal — {{ $weekCompact }}
            </div>
        </div>
        <div class="toolbar no-print">
            <button class="btn" onclick="window.history.back()">Volver</button>
            <button class="btn" onclick="location.reload()">Actualizar</button>
            <button class="btn btn-primary" onclick="window.print()">Imprimir / PDF</button>
        </div>
    </header>

    {{-- DATOS DEL ALUMNO --}}
    <div class="grid grid-3">
        <div class="card">
            <h3>Alumno</h3>
            <div class="muted">
                <div><strong>{{ $student->paternal_lastname }} {{ $student->maternal_lastname }}, {{ $student->names }}</strong></div>
                <div>Grupo: {{ optional($student->group)->name ?? '—' }}</div>
                @if($student->email)
                    <div>Email: {{ $student->email }}</div>
                @endif
            </div>
        </div>

        <div class="card">
            <h3>Resumen</h3>
            <div class="kpi">
                <div class="big">{{ $summary['avg'] ?? '—' }}</div>
                <div class="unit">promedio</div>
            </div>
            <div class="muted" style="margin-top:6px">
                Completadas: <strong>{{ $summary['completed'] ?? '—' }}</strong> ·
                Pendientes: <strong>{{ $summary['pending'] ?? '—' }}</strong>
            </div>
        </div>

        <div class="card">
            <h3>Asistencia</h3>
            <div class="muted">
                Presentes: <strong>{{ $attendance['present'] ?? '—' }}</strong><br>
                Faltas: <strong>{{ $attendance['absent'] ?? '—' }}</strong>
                @if(isset($attendance['justified'])) · Justificadas: <strong>{{ $attendance['justified'] }}</strong>@endif
                @if(isset($attendance['late'])) · Retardos: <strong>{{ $attendance['late'] }}</strong>@endif
            </div>
        </div>
    </div>

    {{-- NOMENCLATURA (entre resumen y tabla) --}}
    <div class="card" style="margin-top:16px">
        <h3>Nomenclatura</h3>
        <div class="muted">
            <ul style="margin:6px 0 0 16px">
                <li><strong>J</strong> = Trabajo Justificado <span style="color:#64748b">(no se tiene que hacer)</span></li>
                <li><strong>P</strong> = Trabajo Pendiente <span style="color:#64748b">(se cuenta como cero)</span></li>
                <li><strong>0</strong> = Sin entregar</li>
                <li><strong>1–10</strong> = Calificación del trabajo</li>
            </ul>
        </div>
    </div>

    {{-- CALIFICACIONES/ENTREGAS DE LA SEMANA --}}
    <div class="card" style="margin-top:16px">
        <h3>Trabajos y calificaciones de la semana</h3>
        <div class="muted" style="margin-bottom:8px">Incluye tareas, exámenes, proyectos u otras evidencias.</div>

        <table>
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Materia</th>
                <th>Trabajo</th>
                <th>Peso</th>
                <th>Calificación</th>
                <th>Comentario</th>
            </tr>
            </thead>
            <tbody>
            @forelse($grades as $item)
                @php
                    $dateOut = isset($item['date']) && $item['date']
                        ? ( $item['date'] instanceof Carbon ? $item['date']->format('Y-m-d') : Carbon::parse($item['date'])->format('Y-m-d') )
                        : null;

                    $pct = isset($item['max'], $item['score']) && $item['max'] > 0
                        ? round(($item['score'] / $item['max']) * 100)
                        : null;

                    $scoreClass = 'score';
                    if (!is_null($pct)) {
                        if ($pct >= 80) $scoreClass .= ' ok';
                        elseif ($pct >= 60) $scoreClass .= ' warn';
                        else $scoreClass .= ' bad';
                    }
                @endphp
                <tr>
                    <td>{{ $dateOut ?? '—' }}</td>
                    <td class="subject">{{ $item['subject'] ?? '—' }}</td>
                    <td>{{ $item['work'] ?? '—' }}</td>
                    <td>
                        @if(isset($item['weight']))
                            <span class="pill">{{ $item['weight'] }}%</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="{{ $scoreClass }}">
                        @if(isset($item['score']))
                            {{ $item['score'] }} @if(isset($item['max']))/ {{ $item['max'] }} @endif
                            @if(!is_null($pct)) ({{ $pct }}%) @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="muted">{{ $item['comment'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No hay registros para esta semana.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- OBSERVACIONES DEL DOCENTE / PRECEPTOR --}}
    @if(!empty($teacherNotes))
        <div class="card" style="margin-top:16px">
            <h3>Observaciones</h3>
            <ul style="margin:8px 0 0 16px">
                @foreach($teacherNotes as $note)
                    <li style="margin-bottom:6px">{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- PIE --}}
    <footer>
        Generado el {{ now()->translatedFormat('Y-m-d H:i') }} ·
        Semana: {{ $weekCompact }}.
        @if(!empty($brand['school_name'])) {{ $brand['school_name'] }}. @endif
    </footer>
</div>

</body>
</html>
