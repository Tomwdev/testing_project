@props(['title', 'href' => null, 'type' => 'note'])

@php
    $typeColors = [
        'note' => 'hover:border-blue-600',
        'project' => 'hover:border-green-600',
        'concept' => 'hover:border-purple-600',
    ];
    $borderColor = $typeColors[$type] ?? 'hover:border-blue-600';
@endphp

<div
    {{ $attributes(['class' => "p-4 bg-white/5 rounded-xl border border-transparent {$borderColor} group transition-colors duration-300"]) }}>
    <div class="flex justify-between items-start">
        <div class="flex-1">
            @if ($href)
                <a href="{{ $href }}" class="font-bold text-lg group-hover:text-white/90 transition-colors">
                    {{ $title }}
                </a>
            @else
                <h3 class="font-bold text-lg">{{ $title }}</h3>
            @endif
        </div>
        @if (isset($actions))
            <div class="flex items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>

    @if (isset($tags) && $tags->isNotEmpty())
        <div class="flex flex-wrap gap-2 mt-3">
            {{ $tags }}
        </div>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-3 text-white/70 text-sm">
            {{ $slot }}
        </div>
    @endif
</div>
