<x-filament-panels::page>
    <div class="mb-6">
        {{ $this->form }}
    </div>

    @if($rows->count())
        <div class="overflow-x-auto bg-white border rounded-xl shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Alumno</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Grupo</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Estado</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Firmado el</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Padre/Madre</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $r)
                        <tr>
                            <td class="px-4 py-3 text-gray-900">{{ $r['student_name'] }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $r['group'] }}</td>
                            <td class="px-4 py-3">
                                @if($r['signed'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Firmado</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-700">Sin firma</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $r['signed_at'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $r['parent'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $r['email'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="text-sm text-gray-600">
            Selecciona una <span class="font-medium">semana</span> y un <span class="font-medium">grupo</span> para ver el estatus.
        </div>
    @endif
</x-filament-panels::page>
