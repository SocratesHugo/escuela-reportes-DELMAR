@php
    $parent = $parent ?? null;
    $week   = $week ?? null;
@endphp

<p>Hola {{ $parent->name }},</p>

<p>Estos son los enlaces para revisar el reporte de la <strong>{{ $week->name }}</strong>:</p>

<ul>
@foreach($links as $row)
    <li>
        {{ $row['student_name'] }}:
        <a href="{{ $row['url'] }}" target="_blank">{{ $row['url'] }}</a>
    </li>
@endforeach
</ul>

<p>Al abrir el reporte verás un botón “Firmar reporte” para confirmar que lo revisaste.</p>

<p>Gracias.</p>
