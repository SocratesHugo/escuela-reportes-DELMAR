{{-- resources/views/filament/components/branding-preview.blade.php --}}
@php
    use Illuminate\Support\Str;

    // Normalizar: si $get no existe (fuera de un Filament Form), queda en null
    $__get = $get ?? null;

    /**
     * Helper: lee desde $__get si existe (Filament Forms); si no, usa default
     * o alguna variable suelta con el mismo nombre que se haya pasado a la vista.
     */
    $read = function (string $key, $default = null) use ($__get) {
        try {
            if (isset($__get)) {
                // En Filament v3, $get puede ser invocable o tener ->get($key)
                if (is_callable($__get)) {
                    return $__get($key) ?? $default;
                }
                if (is_object($__get) && method_exists($__get, 'get')) {
                    return $__get->get($key) ?? $default;
                }
            }
        } catch (\Throwable $e) {
            // Si no estamos en un form o falla la lectura, devolvemos default
        }

        // Si a la vista le pasaste una variable con el mismo nombre, úsala
        return $GLOBALS[$key] ?? $default;
    };

    // Valores con fallback
    $schoolName = (string) $read('school_name', $schoolName ?? config('app.name'));
    $logoPath   = $read('logo_path',   $logoPath   ?? null);
    $primary    = $read('primary_color',   $primary    ?? '#0ea5e9');
    $secondary  = $read('secondary_color', $secondary  ?? '#8b5cf6');
    $textColor  = $read('text_color',      $textColor  ?? '#111827');

    // Resolver URL del logo (si está en storage)
    $logoUrl = null;
    if (is_string($logoPath) && $logoPath !== '') {
        $logoUrl = Str::startsWith($logoPath, ['http://', 'https://', '/'])
            ? $logoPath
            : asset('storage/' . ltrim($logoPath, '/'));
    }
@endphp

<div class="flex items-center gap-3">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="Logo" class="h-10 w-auto rounded">
    @endif
    <div>
        <div class="font-bold" style="color: {{ $textColor }}">{{ $schoolName }}</div>
        <div class="text-xs opacity-70">
            <span class="inline-block w-3 h-3 rounded-sm align-middle mr-1" style="background: {{ $primary }}"></span>Primario
            <span class="inline-block w-3 h-3 rounded-sm align-middle ml-3 mr-1" style="background: {{ $secondary }}"></span>Secundario
        </div>
    </div>
</div>
