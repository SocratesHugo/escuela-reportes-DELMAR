<div class="space-y-3">
    <div>
        <div class="text-xs text-gray-500">Asunto</div>
        <div class="font-medium">{{ $subject }}</div>
    </div>

    <div>
        <div class="text-xs text-gray-500">Cuerpo</div>
        <div class="whitespace-pre-line">{{ $body }}</div>
    </div>

    @if(!empty($note))
        <div class="text-xs text-gray-500 border-t pt-2 whitespace-pre-line">
            {{ $note }}
        </div>
    @endif
</div>
