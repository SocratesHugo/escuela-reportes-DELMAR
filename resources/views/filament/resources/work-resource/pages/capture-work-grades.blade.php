{{-- resources/views/filament/resources/work-resource/pages/capture-work-grades.blade.php --}}
@php
    use App\Support\Grades;

    $weekdayLabel = function ($w) {
        $mapNum = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
        $mapStr = ['mon' => 'Lunes', 'tue' => 'Martes', 'wed' => 'Miércoles', 'thu' => 'Jueves', 'fri' => 'Viernes'];
        if (is_numeric($w))   return $mapNum[(int) $w] ?? (string) $w;
        if (is_string($w))    return $mapStr[strtolower($w)] ?? ucfirst($w);
        return (string) $w;
    };
@endphp

<x-filament-panels::page>
    {{-- ===== Overrides Dark Mode (mejor contraste) ===== --}}
    <style>
        .dark .fi-body,
        .dark .fi-section,
        .dark .fi-main,
        .dark .fi-ta,
        .dark label,
        .dark th,
        .dark td { color: #F9FAFB !important; }

        .dark .fi-section-content,
        .dark .fi-panel,
        .dark .fi-card,
        .dark .fi-table { background-color: #111827 !important; }

        .dark thead tr th { background-color: #1F2937 !important; color: #E5E7EB !important; }
        .dark tbody tr td { background-color: #0F172A !important; }

        .dark input,
        .dark select,
        .dark textarea,
        .dark .fi-input,
        .dark .fi-select {
            background-color: #1F2937 !important;
            border-color: #374151 !important;
            color: #F9FAFB !important;
        }
        .dark input::placeholder, .dark textarea::placeholder { color: #9CA3AF !important; }

        .dark .fi-btn:not(.fi-btn-color-primary) {
            background-color: #374151 !important;
            color: #F9FAFB !important;
            border-color: #4B5563 !important;
        }
        .dark .fi-btn-color-primary {
            background-color: #F59E0B !important;
            border-color: #D97706 !important;
            color: #111827 !important;
        }

        .dark .bg-gray-100 { background-color: #1F2937 !important; }
        .dark .text-gray-700 { color: #E5E7EB !important; }
        .dark .text-gray-400, .dark .text-gray-500 { color: #9CA3AF !important; }
        .dark .bg-gray-50 { background-color: #111827 !important; }
        .dark .border-gray-200 { border-color: #374151 !important; }

        .dark .pill-preview { background-color: #1F2937 !important; color: #E5E7EB !important; }
    </style>

    {{-- ===== Chips de contexto ===== --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        @if($assignmentLabel)
            <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
                {{ $assignmentLabel }}
            </span>
        @endif

        @if($weekLabel)
            <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
                {{ $weekLabel }}
            </span>
        @endif

        @if(property_exists($this, 'editFilters') && !$this->editFilters)
            <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-50 text-gray-400">
                (bloqueado)
            </span>
        @endif
    </div>

    {{-- ===== Filtros (Materia–Grupo / Semana) ===== --}}
    <div class="mb-6">
        {{ $this->form }}
    </div>

    {{-- ===== Matriz ===== --}}
    <div class="rounded-xl border border-gray-200 bg-white dark:bg-[#0B1020] overflow-x-auto">
        @if($students->isEmpty() || $works->isEmpty())
            <div class="p-8 text-center text-sm text-gray-500">
                @if(!$assignmentId || !$weekId)
                    Selecciona <strong>Materia–Grupo</strong> y <strong>Semana</strong> para comenzar.
                @elseif($students->isEmpty())
                    No hay alumnos en el grupo seleccionado.
                @elseif($works->isEmpty())
                    Aún no hay trabajos en esta semana. Usa los botones de la parte superior para crearlos.
                @endif
            </div>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-[#1F2937]">
                    <tr>
                        <th class="sticky left-0 z-10 bg-gray-50 dark:bg-[#1F2937] px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 w-64">
                            Nombre del alumno
                        </th>

                        @foreach ($works as $work)
                            <th class="px-4 py-2 text-left align-bottom min-w-[260px]">
                                <div class="text-xs text-gray-500">{{ $weekdayLabel($work->weekday) }}</div>
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $work->name ?: 'Trabajo' }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($students as $student)
                        <tr>
                            <td class="sticky left-0 z-10 bg-white dark:bg-[#0B1020] px-4 py-3 font-medium text-gray-800 dark:text-gray-100">
                                {{ $student->paternal_lastname }} {{ $student->maternal_lastname }} {{ $student->names }}
                            </td>

                            @foreach ($works as $work)
                                @php
                                    $cellKey   = "matrix.{$student->id}.{$work->id}";
                                    $modalId   = "comment-{$student->id}-{$work->id}";
                                    $status    = data_get($this, "{$cellKey}.status");
                                    $score     = data_get($this, "{$cellKey}.score");
                                    [$lbl, $cls] = Grades::badge($score, $status);
                                    $hasComment = (bool) data_get($this, "{$cellKey}.comment");
                                @endphp
                                <td class="px-4 py-3 align-top">
                                    <div class="flex items-center gap-2">
                                        <select
                                            wire:model.defer="{{ $cellKey }}.status"
                                            class="fi-input w-20 rounded-md border-gray-300 focus:border-primary-500 focus:ring-primary-500"
                                            title="Estatus"
                                        >
                                            <option value="normal">—</option>
                                            <option value="P">P</option>
                                            <option value="J">J</option>
                                        </select>

                                        <input
                                            type="number" step="0.1" min="0" max="10"
                                            wire:model.defer="{{ $cellKey }}.score"
                                            class="fi-input w-24 rounded-md border-gray-300 focus:border-primary-500 focus:ring-primary-500"
                                            placeholder="0.0"
                                            title="Calificación"
                                        />

                                        {{-- Vista previa del estatus según helper --}}
                                        <span class="px-2 py-0.5 rounded text-xs {{ $cls }}" title="Estatus calculado">
                                            {{ $lbl }}
                                        </span>

                                        {{-- Comentario --}}
                                        <button
                                            type="button"
                                            x-data
                                            x-on:click="$dispatch('open-modal', { id: '{{ $modalId }}' })"
                                            class="inline-flex items-center justify-center rounded-md border px-2.5 py-1.5 text-xs font-medium
                                                   {{ $hasComment ? 'border-primary-300 text-primary-700 bg-primary-50 hover:bg-primary-100 dark:bg-[#1f2937] dark:text-amber-300' : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:text-gray-100' }}"
                                            title="{{ $hasComment ? 'Editar comentario' : 'Agregar comentario' }}"
                                        >
                                            {{ $hasComment ? '💬*' : '💬' }}
                                        </button>
                                    </div>

                                    @if($hasComment)
                                        <div class="mt-2">
                                            <span class="pill-preview inline-block max-w-[220px] truncate rounded-full px-2 py-0.5 text-xs">
                                                {{ data_get($this, "{$cellKey}.comment") }}
                                            </span>
                                        </div>
                                    @endif

                                    <x-filament::modal id="{{ $modalId }}" width="md" display-classes="block">
                                        <x-slot name="header">
                                            <div class="text-base font-semibold">
                                                Comentario — {{ $student->paternal_lastname }} {{ $student->maternal_lastname }} {{ $student->names }}
                                            </div>
                                            <div class="mt-0.5 text-xs text-gray-500">
                                                {{ $weekdayLabel($work->weekday) }} · {{ $work->name ?: 'Trabajo' }}
                                            </div>
                                        </x-slot>

                                        <div class="space-y-3">
                                            <textarea
                                                rows="6"
                                                wire:model.defer="{{ $cellKey }}.comment"
                                                class="fi-input w-full rounded-md border-gray-300 focus:border-primary-500 focus:ring-primary-500"
                                                placeholder="Escribe un comentario para este alumno y trabajo…"
                                            ></textarea>
                                            <p class="text-xs text-gray-400">
                                                Al presionar <strong>Guardar</strong> se almacena inmediatamente.
                                            </p>
                                        </div>

                                        <x-slot name="footer">
                                            <div class="flex items-center gap-2">
                                                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: '{{ $modalId }}' })">
                                                    Cerrar
                                                </x-filament::button>

                                                <x-filament::button
                                                    color="primary"
                                                    wire:click="saveComment({{ $student->id }}, {{ $work->id }})"
                                                    x-on:click="$dispatch('close-modal', { id: '{{ $modalId }}' })"
                                                    wire:loading.attr="disabled"
                                                >
                                                    <span wire:loading.remove wire:target="saveComment({{ $student->id }}, {{ $work->id }})">Guardar</span>
                                                    <span wire:loading wire:target="saveComment({{ $student->id }}, {{ $work->id }})">Guardando…</span>
                                                </x-filament::button>
                                            </div>
                                        </x-slot>
                                    </x-filament::modal>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-3 text-xs text-gray-400">
        Consejo: usa <strong>Guardar todo</strong> en la barra superior para persistir cambios masivos.
    </div>

</x-filament-panels::page>
