<x-filament::page>
    <div class="max-w-3xl">
        <x-filament::section :heading="'Exportar a Excel — Calificaciones y promedios'">
            <div class="text-sm text-gray-600 mb-3">
                Selecciona filtros opcionales y presiona <b>Exportar a Excel</b>. Se generarán dos hojas:
                <em>Resumen</em> (promedios por trimestre) y <em>Detalle</em> (todas las capturas).
            </div>
            {{ $this->form }}
        </x-filament::section>
    </div>
</x-filament::page>
