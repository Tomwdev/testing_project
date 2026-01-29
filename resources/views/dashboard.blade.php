<x-layout>
    <x-page-heading>Home</x-page-heading>

    <x-flash-message />

    @guest
        <div class="text-center mb-10">
            <div class="flex justify-center gap-4">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">
            <x-panel>
                <div class="text-center">
                    <h3 class="font-bold text-xl mb-2">Notes</h3>
                    <p class="text-white/70 text-sm">Create and organize your personal notes</p>
                </div>
            </x-panel>
            <x-panel>
                <div class="text-center">
                    <h3 class="font-bold text-xl mb-2">Projects</h3>
                    <p class="text-white/70 text-sm">Track your projects and their status</p>
                </div>
            </x-panel>
            <x-panel>
                <div class="text-center">
                    <h3 class="font-bold text-xl mb-2">Concepts</h3>
                    <p class="text-white/70 text-sm">Document learning concepts and ideas</p>
                </div>
            </x-panel>
        </div>
    @endguest

    @auth
        <div class="flex justify-center gap-4 mb-10">
            <x-link-button href="/notes/create">New Note</x-link-button>
            <x-link-button href="/projects/create" variant="secondary">New Project</x-link-button>
            <x-link-button href="/concepts/create" variant="secondary">New Concept</x-link-button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Notes --}}
            <div>
                <div class="flex justify-between items-center mb-4">
                    <x-section-heading>Recent Notes</x-section-heading>
                    <a href="/notes" class="text-sm text-blue-400 hover:underline">View all</a>
                </div>
                @if ($recentNotes->isEmpty())
                    <x-panel>
                        <p class="text-center text-white/50 text-sm">No notes yet</p>
                    </x-panel>
                @else
                    <div class="space-y-3">
                        @foreach ($recentNotes as $note)
                            <x-content-card :title="$note->title" :href="'/notes/' . $note->id" type="note">
                                <x-slot:tags>
                                    @foreach ($note->tags->take(2) as $tag)
                                        <x-tag :tag="$tag" size="small" />
                                    @endforeach
                                </x-slot:tags>
                            </x-content-card>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Projects --}}
            <div>
                <div class="flex justify-between items-center mb-4">
                    <x-section-heading>Recent Projects</x-section-heading>
                    <a href="/projects" class="text-sm text-blue-400 hover:underline">View all</a>
                </div>
                @if ($recentProjects->isEmpty())
                    <x-panel>
                        <p class="text-center text-white/50 text-sm">No projects yet</p>
                    </x-panel>
                @else
                    <div class="space-y-3">
                        @foreach ($recentProjects as $project)
                            <x-content-card :title="$project->title" :href="'/projects/' . $project->id" type="project">
                                <x-slot:actions>
                                    <span
                                        class="text-2xs px-2 py-0.5 rounded-full
                                        @if ($project->status === 'active') bg-green-800/50 text-green-300
                                        @elseif($project->status === 'completed') bg-blue-800/50 text-blue-300
                                        @else bg-gray-800/50 text-gray-300 @endif">
                                        {{ ucfirst($project->status) }}
                                    </span>
                                </x-slot:actions>
                                <x-slot:tags>
                                    @foreach ($project->tags->take(2) as $tag)
                                        <x-tag :tag="$tag" size="small" />
                                    @endforeach
                                </x-slot:tags>
                            </x-content-card>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Concepts --}}
            <div>
                <div class="flex justify-between items-center mb-4">
                    <x-section-heading>Recent Concepts</x-section-heading>
                    <a href="/concepts" class="text-sm text-blue-400 hover:underline">View all</a>
                </div>
                @if ($recentConcepts->isEmpty())
                    <x-panel>
                        <p class="text-center text-white/50 text-sm">No concepts yet</p>
                    </x-panel>
                @else
                    <div class="space-y-3">
                        @foreach ($recentConcepts as $concept)
                            <x-content-card :title="$concept->title" :href="'/concepts/' . $concept->id" type="concept">
                                <x-slot:tags>
                                    @foreach ($concept->tags->take(2) as $tag)
                                        <x-tag :tag="$tag" size="small" />
                                    @endforeach
                                </x-slot:tags>
                            </x-content-card>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Tags --}}
        @if ($tags->isNotEmpty())
            <div class="mt-10">
                <div class="flex justify-between items-center mb-4">
                    <x-section-heading>Tags</x-section-heading>
                    <a href="/tags" class="text-sm text-blue-400 hover:underline">View all</a>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach ($tags as $tag)
                        <x-tag :tag="$tag" />
                    @endforeach
                </div>
            </div>
        @endif
    @endauth
</x-layout>
