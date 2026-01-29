<x-layout>
    <x-page-heading>Tech Stack</x-page-heading>

    <x-flash-message />

    <div class="mb-6">
        <p class="text-white/70 text-center">Browse all available technology tags used to categorize content</p>
    </div>

    @if ($tags->isEmpty())
        <x-panel>
            <p class="text-center text-white/70">No tags available yet.</p>
        </x-panel>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($tags as $tag)
                <a href="/tags/{{ $tag->slug }}" class="block">
                    <x-panel class="text-center hover:bg-white/10 transition-colors">
                        <h3 class="font-bold text-xl mb-2">{{ $tag->name }}</h3>
                        <div class="text-sm text-white/60 space-x-4">
                            <span>{{ $tag->notes_count }} {{ Str::plural('note', $tag->notes_count) }}</span>
                            <span>{{ $tag->projects_count }} {{ Str::plural('project', $tag->projects_count) }}</span>
                            <span>{{ $tag->concepts_count }} {{ Str::plural('concept', $tag->concepts_count) }}</span>
                        </div>
                    </x-panel>
                </a>
            @endforeach
        </div>
    @endif
</x-layout>
