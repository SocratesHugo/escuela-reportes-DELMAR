@php
    $weekdayOrder = ['mon','tue','wed','thu','fri'];
    $dayLabel = fn($abbr) => ['mon'=>'Lunes','tue'=>'Martes','wed'=>'Miércoles','thu'=>'Jueves','fri'=>'Viernes'][$abbr] ?? $abbr;
    $fmt = fn($v) => is_null($v) ? '—' : number_format((float)$v, 2);
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Boleta semanal</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1,h2,h3 { margin: 0 0 6px; }
        .header { margin-bottom: 10px; }
        .muted { color: #666; }
        .box { border: 1px solid #ddd; padding: 10px; border-radius: 6px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .tag { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
        .tag-P { background: #fde047; } /* Amarillo: Pendiente */
        .tag-J { background: #86efac; } /* Verde: Justificado */
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Boleta semanal</h1>
        <div class="muted">
            Alumno: <strong>{{ $student->full_name ?? ($student->paternal_lastname.' '.$student->maternal_lastname.' '.$student->names) }}</strong><br>
            Grupo: <strong>{{ $student->group->name ?? '—' }}</strong><br>
            Semana: <strong>{{ $week->name ?? ('#'.$week->id) }}</strong>
        </div>
    </div>

    <div class="box">
        <h3>Trabajos de la semana</h3>
        @foreach ($weekdayOrder as $abbr)
            @php $day = $byDay[$abbr] ?? null; @endphp
            @if ($day && count($day['items']))
                <h4>{{ $dayLabel($abbr) }}</h4>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 35%;">Materia</th>
                            <th>Trabajo</th>
                            <th style="width: 12%;">Estatus</th>
                            <th style="width: 12%;">Calificación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($day['items'] as $w)
                            @php
                                $g = $grades->get($w->id);
                                $status = $g->status ?? 'NORMAL';
                                $score  = $g->score;
                                $subject = optional($w->assignment?->subject)->name ?? 'Materia';
                            @endphp
                            <tr>
                                <td>{{ $subject }}</td>
                                <td>{{ $w->name }}</td>
                                <td>
                                    @if ($status === 'P')
                                        <span class="tag tag-P">Pendiente</span>
                                    @elseif ($status === 'J')
                                        <span class="tag tag-J">Justificado</span>
                                    @else
                                        Entregado
                                    @endif
                                </td>
                                <td class="right">{{ $fmt($score) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <br>
            @endif
        @endforeach
    </div>

    <div class="box">
        <h3>Promedios por materia</h3>
        <table>
            <thead>
                <tr>
                    <th>Materia</th>
                    <th style="width: 20%;" class="right">Promedio</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subjectAverages as $row)
                    <tr>
                        <td>{{ $row['subject'] }}</td>
                        <td class="right">{{ is_null($row['average']) ? '—' : number_format($row['average'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">Sin datos para promediar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</body>
</html>
