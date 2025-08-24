@props([
  'title' => '',
  'value' => null,
  'help'  => '',
  'icon'  => 'heroicon-o-chart-bar',
  'tone'  => 'primary', // primary|indigo|amber|red|green|slate
])

@php
$tones = [
  'primary' => 'bg-blue-50 text-blue-700 ring-blue-200',
  'indigo'  => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
  'amber'   => 'bg-amber-50 text-amber-700 ring-amber-200',
  'red'     => 'bg-rose-50 text-rose-700 ring-rose-200',
  'green'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  'slate'   => 'bg-slate-50 text-slate-700 ring-slate-200',
][$tone];
@endphp

<div class="rounded-xl ring-1 p-4 {{ $tones }}">
  <div class="flex items-start gap-3">
    <x-dynamic-component :component="$icon" class="w-6 h-6 opacity-70"/>
    <div class="flex-1">
      <div class="text-sm font-medium">{{ $title }}</div>
      @if($help)<div class="mt-0.5 text-xs opacity-70">{{ $help }}</div>@endif
      <div class="mt-2 text-4xl font-extrabold tabular-nums">
        {{ $value === null ? '—' : $value }}
      </div>
    </div>
  </div>
</div>
