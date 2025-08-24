{{-- Snapshot Grupo–Materia (admin) --}}
<x-filament-panels::page>
    <div x-data @redirect.window="window.location = $event.detail.url"></div>

    {{-- Filtros --}}
    <div class="mb-6">
        {{ $this->form }}
    </div>

    @if (!$week || !$group || !$gst)
        <x-filament::card>
            <div class="text-sm text-gray-600">
                Selecciona <strong>Semana</strong>, <strong>Grupo</strong> y <strong>Materia del grupo</strong> para ver el snapshot.
            </div>
        </x-filament::card>
        @php return; @endphp
    @endif

    {{-- Encabezado contextual --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <x-filament::card>
            <div class="text-sm text-gray-500">Semana</div>
            <div class="mt-1 text-base font-semibold">{{ $week->name }}</div>
            <div class="text-xs text-gray-400">
                {{ optional($week->starts_at)->format('Y-m-d') }} a {{ optional($week->ends_at)->format('Y-m-d') }}
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Grupo</div>
            <div class="mt-1 text-base font-semibold">{{ $group->name }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Materia</div>
            <div class="mt-1 text-base font-semibold">
                {{ $gst->subject?->name ?? 'Materia' }}
                <span class="text-xs text-gray-500 block">
                    {{ trim(($gst->teacher?->paternal_lastname ?? '').' '.($gst->teacher?->names ?? '')) }}
                </span>
            </div>
        </x-filament::card>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <x-filament::card>
            <div class="text-sm text-gray-500">Promedio</div>
            <div class="text-3xl font-bold mt-1">{{ number_format($avg, 2) }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Cobertura</div>
            @php $cov = $cellsTotal ? round(100*$cellsFilled/$cellsTotal,1) : 0; @endphp
            <div class="text-3xl font-bold mt-1">{{ $cov }}%</div>
            <div class="text-xs text-gray-400">{{ $cellsFilled }} / {{ $cellsTotal }} celdas</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Entregados</div>
            <div class="text-2xl font-semibold mt-1">{{ $delivered }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Pendientes (P)</div>
            <div class="text-2xl font-semibold mt-1">{{ $pendings }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">Sin entregar (0)</div>
            <div class="text-2xl font-semibold mt-1">{{ $zeros }}</div>
        </x-filament::card>
    </div>

    {{-- Matriz por alumno × trabajo --}}
    <x-filament::section :heading="'Trabajos y estatus por alumno'">
        @if($students->isEmpty() || $works->isEmpty())
            <div class="p-6 text-sm text-gray-500">
                @if($students->isEmpty())
                    No hay alumnos en el grupo.
                @elseif($works->isEmpty())
                    No existen trabajos en esta semana para esta materia.
                @endif
            </div>
        @else
            @php
                $weekday = function ($w) {
                    $m = ['mon'=>'Lunes','tue'=>'Martes','wed'=>'Miércoles','thu'=>'Jueves','fri'=>'Viernes'];
                    if (is_string($w)) return $m[strtolower($w)] ?? ucfirst($w);
                    $n = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes'];
                    return $n[(int)$w] ?? (string)$w;
                };
            @endphp

            <div class="overflow-x-auto bg-white border rounded-xl shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 w-64">Alumno</th>
                            @foreach($works as $w)
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 min-w-[200px]">
                                    <div class="text-xs text-gray-500">{{ $weekday($w->weekday) }}</div>
                                    <div class="font-medium">{{ $w->name ?: 'Trabajo' }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($students as $stu)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">
                                    {{ $stu->paternal_lastname }} {{ $stu->maternal_lastname }} {{ $stu->names }}
                                </td>

                                @foreach($works as $w)
                                    @php
                                        $g = ($grades->get($w->id) ?? collect())->firstWhere('student_id', $stu->id);
                                        $status = $g->status ?? null; // P/J/ null
                                        $score  = $g->score  ?? null;

                                        $chip = ['label'=>'—','class'=>'bg-gray-100 text-gray-700'];
                                        if ($status === 'P')       $chip = ['label'=>'P','class'=>'bg-amber-100 text-amber-700'];
                                        elseif ($status === 'J')  $chip = ['label'=>'J','class'=>'bg-emerald-100 text-emerald-700'];
                                        elseif (is_numeric($score)) {
                                            if ((float)$score == 0.0) $chip = ['label'=>'0.00','class'=>'bg-rose-100 text-rose-700'];
                                            else $chip = ['label'=>number_format((float)$score,2),'class'=>'bg-sky-100 text-sky-800'];
                                        }
                                    @endphp
                                    <td class="px-4 py-2 align-top">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $chip['class'] }}">
                                            {{ $chip['label'] }}
                                        </span>
                                        @if(!empty($g?->comment))
                                            <div class="text-xs text-gray-500 mt-1 line-clamp-2" title="{{ $g->comment }}">
                                                {{ $g->comment }}
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
