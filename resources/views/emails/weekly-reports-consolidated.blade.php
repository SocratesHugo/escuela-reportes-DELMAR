@php
    $body = $s?->body_template ?: 'Hola {{parent_name}}, aquí están los reportes de {{week_name}}: {{links}}';
    $parentName = $isParent ? ($who->name ?? 'Padre/Madre') : ($who->full_name ?? 'Alumno');
    $linksHtml = '<ul style="margin:0;padding-left:16px">';
    foreach ($items as $it) {
        $linksHtml .= '<li><a href="'.$it['url'].'" target="_blank">'.e($it['student']->full_name).' — Ver reporte</a></li>';
    }
    $linksHtml .= '</ul>';

    $repl = [
        '{{parent_name}}'       => e($parentName),
        '{{week_name}}'         => e($week->name),
        '{{links}}'             => $linksHtml,
    ];

    $html = $body;
    foreach ($repl as $k => $v) $html = str_replace($k, $v, $html);
@endphp

<!doctype html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: ui-sans-serif, system-ui; color:#111; line-height:1.5;">
    <div style="max-width:640px;margin:0 auto;">
        <p>{!! $html !!}</p>

        @if($isParent)
            <p style="font-size:12px;color:#666">El enlace incluye la opción de firmar el reporte. Este botón
            no aparece para el alumno.</p>
        @endif

        <p style="font-size:12px;color:#999">Este enlace expira automáticamente.</p>
    </div>
</body>
</html>
