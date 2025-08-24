<table class="min-w-full text-sm">
  <thead class="bg-gray-50">
    <tr>
      <th class="px-3 py-2 text-left w-full">Grupo</th>
      <th class="px-3 py-2 text-right">P</th>
      <th class="px-3 py-2 text-right">Ver</th>
    </tr>
  </thead>
  <tbody class="divide-y">
    @forelse($rows as $r)
      <tr>
        <td class="px-3 py-2">{{ $r['label'] }}</td>
        <td class="px-3 py-2 text-right font-semibold text-amber-700">{{ $r['p'] }}</td>
        <td class="px-3 py-2 text-right">
          @if(!empty($r['link']))
            <a href="{{ $r['link'] }}" class="text-primary-700 text-xs underline">Snapshot</a>
          @else <span class="text-gray-400">—</span> @endif
        </td>
      </tr>
    @empty
      <tr><td class="px-3 py-3 text-gray-500" colspan="3">Sin datos.</td></tr>
    @endforelse
  </tbody>
</table>
