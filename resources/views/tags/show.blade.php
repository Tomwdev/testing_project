<x-layout>
    <x-page-heading>{{ $tag->name }}</x-page-heading>

    <x-flash-message />

    <div class="mb-6">
        <a href="/tags" class="text-white/70 hover:text-white transition-colors">&larr; Back to Tech Stack</a>
    </div>

    <p class="text-white/70 text-center mb-8">Content tagged with "{{ $tag->name }}"</p>

    {{-- Notes Section --}}
    @if ($tag->notes->isNotEmpty())
        <div class="mb-10">
            <x-section-heading>Notes</x-section-heading>
            <div class="space-y-4 mt-4">
                @foreach ($tag->notes as $note)
                    <x-content-card :title="$note->title" :href="'/notes/' . $note->id" type="note">
                        <x-slot:tags>
                            @foreach ($note->tags as $noteTag)
                                <x-tag :tag="$noteTag" size="small" />
                            @endforeach
                        </x-slot:tags>
                        {{ Str::limit($note->body, 100) }}
                    </x-content-card>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Projects Section --}}
    @if ($tag->projects->isNotEmpty())
        <div class="mb-10">
            <x-section-heading>Projects</x-section-heading>
            <div class="space-y-4 mt-4">
                @foreach ($tag->projects as $project)
                    <x-content-card :title="$project->title" :href="'/projects/' . $project->id" type="project">
                        <x-slot:actions>
                            <span
                                class="text-xs px-2 py-1 rounded-full
                                @if ($project->status === 'active') bg-green-800/50 text-green-300
                                @elseif($project->status === 'completed') bg-blue-800/50 text-blue-300
                                @else bg-gray-800/50 text-gray-300 @endif">
                                {{ ucfirst($project->status) }}
                            </span>
                        </x-slot:actions>
                        <x-slot:tags>
                            @foreach ($project->tags as $projectTag)
                                <x-tag :tag="$projectTag" size="small" />
                            @endforeach
                        </x-slot:tags>
                        {{ Str::limit($project->description, 100) }}
                    </x-content-card>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Concepts Section --}}
    @if ($tag->concepts->isNotEmpty())
        <div class="mb-10">
            <x-section-heading>Concepts</x-section-heading>
            <div class="space-y-4 mt-4">
                @foreach ($tag->concepts as $concept)
                    <x-content-card :title="$concept->title" :href="'/concepts/' . $concept->id" type="concept">
                        <x-slot:tags>
                            @foreach ($concept->tags as $conceptTag)
                                <x-tag :tag="$conceptTag" size="small" />
                            @endforeach
                        </x-slot:tags>
                        {{ Str::limit($concept->description, 100) }}
                    </x-content-card>
                @endforeach
            </div>
        </div>
    @endif

    @if ($tag->notes->isEmpty() && $tag->projects->isEmpty() && $tag->concepts->isEmpty())
        <x-panel>
            <p class="text-center text-white/70">No content has been tagged with "{{ $tag->name }}" yet.</p>
        </x-panel>
    @endif
</x-layout>
