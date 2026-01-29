<x-layout>
    <x-page-heading>{{ $concept->title }}</x-page-heading>

    <x-flash-message />

    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="/concepts" class="text-white/70 hover:text-white transition-colors">&larr; Back to Concepts</a>
        </div>

        <x-panel>
            @if ($concept->tags->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach ($concept->tags as $tag)
                        <x-tag :tag="$tag" />
                    @endforeach
                </div>
            @endif

            @if ($concept->description)
                <div class="prose prose-invert max-w-none">
                    {!! nl2br(e($concept->description)) !!}
                </div>
            @else
                <p class="text-white/50 italic">No description provided.</p>
            @endif

            <div class="mt-6 pt-6 border-t border-white/10 text-sm text-white/50">
                Created {{ $concept->created_at->diffForHumans() }}
                @if ($concept->updated_at->ne($concept->created_at))
                    &middot; Updated {{ $concept->updated_at->diffForHumans() }}
                @endif
            </div>
        </x-panel>

        @can('update', $concept)
            <div class="flex justify-between items-center mt-6">
                <x-link-button href="/concepts/{{ $concept->id }}/edit" variant="secondary">Edit Concept</x-link-button>

                <form method="POST" action="/concepts/{{ $concept->id }}"
                    onsubmit="return confirm('Are you sure you want to delete this concept?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="bg-red-800 hover:bg-red-700 rounded py-2 px-6 font-bold transition-colors">
                        Delete Concept
                    </button>
                </form>
            </div>
        @endcan
    </div>
</x-layout>
