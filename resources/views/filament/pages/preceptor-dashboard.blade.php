<x-filament::page>
    {{-- Filtro + botón Buscar --}}
    <div class="flex items-end gap-3 mb-3">
        <div class="flex-1">
            {{ $this->form }}
        </div>
        <div class="pb-1">
            <x-filament::button wire:click="applyFilter" icon="heroicon-o-magnifying-glass">
                Buscar
            </x-filament::button>
        </div>
    </div>

    {{-- Leyenda de la semana seleccionada --}}
    @if($selectedWeekLabel)
        <div class="text-xs text-gray-500 mb-4">
            {{ $selectedWeekLabel }}
        </div>
    @endif

    @if($rows->isEmpty())
        <x-filament::section>
            <div class="text-sm text-gray-500">
                No hay alumnos preceptuados o no hay datos para la semana seleccionada.
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-3">Alumno</th>
                            <th class="text-left py-2 px-3">Grupo</th>
                            <th class="text-left py-2 px-3">Promedio semanal</th>
                            <th class="text-left py-2 px-3">Pendientes</th>
                            <th class="text-left py-2 px-3">Justificados</th>
                            <th class="text-left py-2 px-3">Sin Entregar</th>
                            <th class="text-left py-2 px-3">Entregados</th>
                            <th class="text-left py-2 px-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr class="border-b">
                                <td class="py-2 px-3">{{ $r['full_name'] }}</td>
                                <td class="py-2 px-3">{{ $r['group'] ?? '-' }}</td>
                                <td class="py-2 px-3">
                                    @if(!is_null($r['avg']))
                                        <span class="font-medium">{{ number_format($r['avg'], 2) }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">
                                        {{ $r['pending_count'] }}
                                    </span>
                                </td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800">
                                        {{ $r['justified_count'] }}
                                    </span>
                                </td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-800">
                                        {{ $r['missing_count'] ?? 0 }}
                                    </span>
                                </td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 rounded bg-green-100 text-green-800">
                                        {{ $r['normal_count'] }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 flex gap-2">
                                    <x-filament::button size="sm" wire:click="openDetail({{ $r['student_id'] }})">
                                        Ver detalle
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="danger"
                                        wire:click="openMissing({{ $r['student_id'] }})"
                                        title="Ver trabajos sin entregar"
                                    >
                                        Sin Entregar
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Modal: Detalle general --}}
    <x-filament::modal
        id="detailModal"
        width="5xl"
        icon="heroicon-o-user"
        alignment="center"
        :close-by-clicking-away="true"
        :close-by-pressing-escape="true"
    >
        <x-slot name="heading">
            Detalle — {{ $detailStudentName ?? '' }}
        </x-slot>

        <div class="space-y-4">
            <div class="flex flex-wrap gap-2">
                <div class="text-sm">
                    <span class="text-gray-600">Promedio:</span>
                    @if(!is_null($detailAvg))
                        <span class="font-medium">{{ number_format($detailAvg, 2) }}</span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </div>
                <div class="text-sm">
                    <span class="text-gray-600">Pendientes:</span>
                    <span class="px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">{{ $detailPending }}</span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-600">Justificados:</span>
                    <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800">{{ $detailJustified }}</span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-600">Sin Entregar (0 sin P/J):</span>
                    <span class="px-2 py-0.5 rounded bg-rose-100 text-rose-800">{{ $detailMissing ?? 0 }}</span>
                </div>
                <div class="text-sm">
                    <span class="text-gray-600">Entregados:</span>
                    <span class="px-2 py-0.5 rounded bg-green-100 text-green-800">{{ $detailNormal }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-3">Materia</th>
                            <th class="text-left py-2 px-3">Trabajo</th>
                            <th class="text-left py-2 px-3">Día</th>
                            <th class="text-left py-2 px-3">Estado</th>
                            <th class="text-left py-2 px-3">Calificación</th>
                            <th class="text-left py-2 px-3">Comentario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($detailRows as $row)
                            @php
                                $isMissing = (isset($row['score']) && (float)$row['score'] === 0.0)
                                             && (!in_array($row['status'], ['P','J'], true));
                                if ($isMissing) {
                                    $badgeClass = 'bg-rose-100 text-rose-800';
                                    $label = 'Sin Entregar';
                                } else {
                                    switch (strtolower($row['status'])) {
                                        case 'p':
                                            $badgeClass = 'bg-yellow-100 text-yellow-800';
                                            $label = 'Pendiente';
                                            break;
                                        case 'j':
                                            $badgeClass = 'bg-blue-100 text-blue-800';
                                            $label = 'Justificado';
                                            break;
                                        default:
                                            $badgeClass = 'bg-green-100 text-green-800';
                                            $label = 'Entregado';
                                            break;
                                    }
                                }
                            @endphp
                            <tr class="border-b">
                                <td class="py-2 px-3">{{ $row['subject'] }}</td>
                                <td class="py-2 px-3">{{ $row['work'] }}</td>
                                <td class="py-2 px-3">{{ $row['weekday'] }}</td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 rounded {{ $badgeClass }}">{{ $label }}</span>
                                </td>
                                <td class="py-2 px-3">
                                    @if(!is_null($row['score']))
                                        {{ number_format((float)$row['score'], 2) }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3">
                                    @if(!empty($row['comment']))
                                        <span title="{{ $row['comment'] }}">{{ \Illuminate\Support\Str::limit($row['comment'], 60) }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-500">No hay trabajos para esta semana.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-slot name="footer">
            <x-filament::button color="gray" wire:click="closeDetail">
                Cerrar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Modal: Sin Entregar (0 sin P/J) --}}
    <x-filament::modal
        id="missingModal"
        width="3xl"
        icon="heroicon-o-exclamation-triangle"
        alignment="center"
        :close-by-clicking-away="true"
        :close-by-pressing-escape="true"
    >
        <x-slot name="heading">
            Sin Entregar — {{ $missingStudentName ?? '' }}
        </x-slot>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 px-3">Materia</th>
                        <th class="text-left py-2 px-3">Trabajo</th>
                        <th class="text-left py-2 px-3">Día</th>
                        <th class="text-left py-2 px-3">Comentario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($missingRows as $row)
                        <tr class="border-b">
                            <td class="py-2 px-3">{{ $row['subject'] }}</td>
                            <td class="py-2 px-3">{{ $row['work'] }}</td>
                            <td class="py-2 px-3">{{ $row['weekday'] }}</td>
                            <td class="py-2 px-3">
                                @if(!empty($row['comment']))
                                    <span title="{{ $row['comment'] }}">{{ \Illuminate\Support\Str::limit($row['comment'], 80) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-gray-500">Sin trabajos sin entregar en esta semana.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot name="footer">
            <x-filament::button color="gray" wire:click="closeMissing">
                Cerrar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament::page>
