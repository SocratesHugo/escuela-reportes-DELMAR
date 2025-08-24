@php
    // Permite array u objeto
    $status    = $row['status'] ?? $row->status ?? null;
    $score     = $row['score']  ?? $row->score  ?? null;
    $day       = $row['day']    ?? $row->day    ?? '—';
    $workName  = $row['work']   ?? $row->work   ?? '—';
    $subject   = $row['subject']?? $row->subject?? '—';
    $comments  = $row['comments'] ?? $row->comments ?? '—';

    $effective = \App\Support\Grades::effective($status, $score);
    $trClass   = \App\Support\Grades::rowClass($status, $effective);
@endphp

<tr class="{{ $trClass }}">
    <td class="px-3 py-2">{{ $day }}</td>
    <td class="px-3 py-2">{{ $workName }}</td>
    <td class="px-3 py-2">{{ $subject }}</td>
    <td class="px-3 py-2">{{ $status ?? '—' }}</td>
    <td class="px-3 py-2">
        @if($effective !== null)
            {{ number_format((float) $effective, 2) }}
        @else
            —
        @endif
    </td>
    <td class="px-3 py-2">{{ $comments }}</td>
</tr>
