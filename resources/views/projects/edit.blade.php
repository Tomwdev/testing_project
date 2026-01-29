<x-layout>
    <x-page-heading>Edit Project</x-page-heading>

    <div class="mb-6">
        <a href="/projects/{{ $project->id }}" class="text-white/70 hover:text-white transition-colors">&larr; Back to
            Project</a>
    </div>

    <x-forms.form method="PUT" action="/projects/{{ $project->id }}">
        <x-forms.input label="Title" name="title" :value="old('title', $project->title)" placeholder="Enter project title" required />

        <x-forms.textarea label="Description" name="description" rows="6"
            placeholder="Describe your project...">{{ old('description', $project->description) }}</x-forms.textarea>

        <x-forms.select label="Status" name="status" required>
            <option value="active" {{ old('status', $project->status) === 'active' ? 'selected' : '' }}>Active</option>
            <option value="completed" {{ old('status', $project->status) === 'completed' ? 'selected' : '' }}>Completed
            </option>
            <option value="archived" {{ old('status', $project->status) === 'archived' ? 'selected' : '' }}>Archived
            </option>
        </x-forms.select>

        @if ($tags->isNotEmpty())
            <div>
                <x-forms.label name="tags" label="Tags (Tech Stack)" />
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($tags as $tag)
                        <label
                            class="inline-flex items-center gap-2 bg-white/10 rounded-lg px-3 py-2 cursor-pointer hover:bg-white/20 transition-colors">
                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                {{ in_array($tag->id, old('tags', $project->tags->pluck('id')->toArray())) ? 'checked' : '' }}
                                class="rounded border-white/30 bg-white/10 text-blue-600 focus:ring-blue-500">
                            <span>{{ $tag->name }}</span>
                        </label>
                    @endforeach
                </div>
                <x-forms.error :error="$errors->first('tags')" />
            </div>
        @endif

        <div class="flex justify-end gap-4">
            <x-link-button href="/projects/{{ $project->id }}" variant="secondary">Cancel</x-link-button>
            <x-forms.button>Update Project</x-forms.button>
        </div>
    </x-forms.form>
</x-layout>
