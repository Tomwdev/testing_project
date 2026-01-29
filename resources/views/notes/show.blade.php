<x-layout>
    <x-page-heading>{{ $note->title }}</x-page-heading>

    <x-flash-message />

    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="/notes" class="text-white/70 hover:text-white transition-colors">&larr; Back to Notes</a>
        </div>

        <x-panel>
            @if ($note->tags->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach ($note->tags as $tag)
                        <x-tag :tag="$tag" />
                    @endforeach
                </div>
            @endif

            <div class="prose prose-invert max-w-none">
                {!! nl2br(e($note->body)) !!}
            </div>

            <div class="mt-6 pt-6 border-t border-white/10 text-sm text-white/50">
                Created {{ $note->created_at->diffForHumans() }}
                @if ($note->updated_at->ne($note->created_at))
                    &middot; Updated {{ $note->updated_at->diffForHumans() }}
                @endif
            </div>
        </x-panel>

        @can('update', $note)
            <div class="flex justify-between items-center mt-6">
                <x-link-button href="/notes/{{ $note->id }}/edit" variant="secondary">Edit Note</x-link-button>

                <form method="POST" action="/notes/{{ $note->id }}"
                    onsubmit="return confirm('Are you sure you want to delete this note?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="bg-red-800 hover:bg-red-700 rounded py-2 px-6 font-bold transition-colors">
                        Delete Note
                    </button>
                </form>
            </div>
        @endcan
    </div>
</x-layout>
