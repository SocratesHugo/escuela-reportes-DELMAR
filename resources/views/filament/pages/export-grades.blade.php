<x-filament::page>
    <div class="mb-6">
        {{ $this->form }}
    </div>

    <x-filament::section>
        <div class="text-sm text-gray-600">
            Este export incluye:
            <ul class="list-disc ml-5 mt-2 space-y-1">
                <li><strong>Resumen:</strong> Promedio por alumno × materia y por trimestre (P=0, J=10).</li>
                <li><strong>Detalle:</strong> Todos los trabajos en el rango, con semana, día, estatus y comentario.</li>
                <li><strong>Matriz por trimestre:</strong> Para la materia/grupo elegidos, columnas “Trabajo 1..N” y promedio por alumno.</li>
            </ul>
        </div>
    </x-filament::section>
</x-filament::page>
