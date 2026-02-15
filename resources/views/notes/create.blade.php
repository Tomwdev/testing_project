<x-layout>
    <x-page-heading>Create Note</x-page-heading>

    <div class="mb-6">
        <a href="/notes" class="text-white/70 hover:text-white transition-colors">&larr; Back to Notes</a>
    </div>

    <x-forms.form method="POST" action="/notes">
        <x-forms.input label="Title" name="title" placeholder="Enter note title" required />

        {{-- NEW: Project Selection Dropdown --}}
        @if ($projects->isNotEmpty())
            <div class="mb-6">
                <x-forms.label name="project_id" label="Project (Optional)" />
                <div class="mt-1">
                    <select name="project_id" id="project_id"
                        class="block w-full rounded-xl border-0 bg-white/10 px-4 py-3 text-white shadow-sm ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-inset focus:ring-blue-500 sm:text-sm sm:leading-6">
                        <option value="" class="bg-gray-900 text-gray-400">No Project</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" class="bg-gray-900 text-white"
                                {{ old('project_id') == $project->id ? 'selected' : '' }}>
                                {{ $project->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <x-forms.error :error="$errors->first('project_id')" />
            </div>
        @endif

        <x-forms.textarea label="Body" name="body" rows="8" placeholder="Write your note content here..."
            required />

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
            <x-link-button href="/notes" variant="secondary">Cancel</x-link-button>
            <x-forms.button>Create Note</x-forms.button>
        </div>
    </x-forms.form>
</x-layout>
