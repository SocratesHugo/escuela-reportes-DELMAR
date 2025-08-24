<x-filament-panels::page>
    <x-filament::card>
        {{ $this->form }}

        <p class="text-sm text-gray-500 mt-4">
            Se enviarán correos con enlaces firmados (sin login). Si está activado “unificar por papá/mamá”,
            cada tutor recibirá un solo correo con los enlaces de todos sus hijos. El botón de firma aparece
            solo a papás/mamás, nunca al alumno.
        </p>
    </x-filament::card>
</x-filament-panels::page>
