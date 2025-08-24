<x-filament::page>
    <div class="space-y-4">
        @if($logo_path)
            <div class="flex items-center gap-3">
                <div class="text-sm text-gray-600">Logo actual:</div>
                <img src="{{ \Illuminate\Support\Facades\Storage::url($logo_path) }}" alt="Logo" class="h-12 w-auto rounded">
            </div>
        @endif

        {{ $this->form }}

        <div>
            <x-filament::button wire:click="save" color="primary">
                Guardar configuración
            </x-filament::button>
        </div>
    </div>
</x-filament::page>
