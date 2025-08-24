<x-filament::page>
    <div class="mb-4">
        {{ $this->form }}
    </div>

    @if($group_id && $rows->isEmpty())
        <x-filament::section>
            <div class="text-sm text-gray-500">
                No hay alumnos en el grupo o no hay datos para la semana seleccionada.
            </div>
        </x-filament::section>
    @elseif(!$group_id)
        <x-filament::section>
            <div class="text-sm text-gray-500">
                Asigna primero un grupo al titular en el módulo de Titulares.
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-3">Alumno</th>
                            <th class="text-left py-2 px-3">Promedio semanal</th>
                            <th class="text-left py-2 px-3">Pendientes</th>
                            <th class="text-left py-2 px-3">Justificados</th>
                            <th class="text-left py-2 px-3">Entregados</th>
                            <th class="text-left py-2 px-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr class="border-b">
                                <td class="py-2 px-3">{{ $r['full_name'] }}</td>
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
                                    <span class="px-2 py-0.5 rounded bg-green-100 text-green-800">
                                        {{ $r['normal_count'] }}
                                    </span>
                                </td>
                                <td class="py-2 px-3">
                                    <x-filament::button
                                        size="sm"
                                        wire:click="openDetail({{ $r['student_id'] }})"
                                    >
                                        Ver detalle
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Modal detalle por alumno --}}
    <x-filament::modal
        width="5xl"
        icon="heroicon-o-user"
        alignment="center"
        :visible="$detailOpen"
        :close-by-clicking-away="true"
        :close-by-pressing-escape="true"
        x-on:close-modal.window="$wire.closeDetail()"
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
                            <tr class="border-b">
                                <td class="py-2 px-3">{{ $row['subject'] }}</td>
                                <td class="py-2 px-3">{{ $row['work'] }}</td>
                                <td class="py-2 px-3">{{ $row['weekday'] }}</td>
                                <td class="py-2 px-3">
                                    @php
                                        $badgeClass = match($row['status']) {
                                            'P' => 'bg-yellow-100 text-yellow-800',
                                            'J' => 'bg-blue-100 text-blue-800',
                                            'NORMAL' => 'bg-green-100 text-green-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        };
                                        $label = match($row['status']) {
                                            'P' => 'Pendiente',
                                            'J' => 'Justificado',
                                            'NORMAL' => 'Entregado',
                                            default => ucfirst(strtolower($row['status'])),
                                        };
                                    @endphp
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
                                    @if($row['comment'])
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
</x-filament::page>
