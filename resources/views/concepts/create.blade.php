<x-layout>
    <x-page-heading>Create Concept</x-page-heading>

    <div class="mb-6">
        <a href="/concepts" class="text-white/70 hover:text-white transition-colors">&larr; Back to Concepts</a>
    </div>

    <x-forms.form method="POST" action="/concepts">
        <x-forms.input label="Title" name="title" placeholder="Enter concept title" required />

        <x-forms.textarea label="Description" name="description" rows="6" placeholder="Describe the concept..." />

        @if ($tags->isNotEmpty())
            <div>
                <x-forms.label name="tags" label="Tags (Tech Stack)" />
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($tags as $tag)
                        <label
                            class="inline-flex items-center gap-2 bg-white/10 rounded-lg px-3 py-2 cursor-pointer hover:bg-white/20 transition-colors">
                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                {{ in_array($tag->id, old('tags', [])) ? 'checked' : '' }}
                                class="rounded border-white/30 bg-white/10 text-blue-600 focus:ring-blue-500">
                            <span>{{ $tag->name }}</span>
                        </label>
                    @endforeach
                </div>
                <x-forms.error :error="$errors->first('tags')" />
            </div>
        @endif

        <div class="flex justify-end gap-4">
            <x-link-button href="/concepts" variant="secondary">Cancel</x-link-button>
            <x-forms.button>Create Concept</x-forms.button>
        </div>
    </x-forms.form>
</x-layout>
