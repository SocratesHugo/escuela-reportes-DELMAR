<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte — {{ $student->full_name }} — {{ $week->name }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;max-width:900px;margin:30px auto;padding:0 16px}</style>
</head>
<body>
  @if(session('status'))
    <div style="background:#ecfdf5;border:1px solid #10b981;padding:12px;border-radius:8px;margin-bottom:16px">
      {{ session('status') }}
    </div>
  @endif

  <h1 style="margin:0 0 8px">Reporte semanal</h1>
  <div style="color:#6b7280;margin-bottom:16px">
    Alumno: <strong>{{ $student->full_name }}</strong> · Grupo: <strong>{{ $student->group?->name ?? '—' }}</strong><br>
    Semana: <strong>{{ $week->name }}</strong>
  </div>

  {{-- Aquí puedes renderizar tu HTML del reporte del alumno (el mismo que ven en Filament) --}}
  <div style="padding:12px 0;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;margin:16px 0">
    <em>Contenido del reporte del alumno (inserta aquí tu vista si ya la tienes).</em>
  </div>

  {{-- Botón de firma: sólo para papás y si no se ha firmado --}}
  @if(($parentViewer ?? false) && empty($alreadySigned))
    <form method="POST" action="{{ route('public.report.sign', [
        'student' => $student->id,
        'week'    => $week->id,
        'aud'     => request('aud'),
        'parent'  => request('parent'),
    ]) }}">
      @csrf
      <button type="submit" style="background:#16a34a;color:#fff;border:0;border-radius:8px;padding:10px 14px;cursor:pointer">
        Firmar reporte como padre/madre
      </button>
    </form>
  @elseif(!empty($alreadySigned))
    <p style="color:#16a34a;margin-top:8px">Reporte ya firmado.</p>
  @endif

  @if(!($parentViewer ?? false))
    <p style="color:#6b7280;margin-top:14px"><small>Este enlace no permite firmar.</small></p>
  @endif
</body>
</html>
