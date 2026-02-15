<x-layout>
    <x-page-heading>{{ $project->title }}</x-page-heading>

    <x-flash-message />

    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="/projects" class="text-white/70 hover:text-white transition-colors">&larr; Back to Projects</a>
        </div>

        <x-panel>
            <div class="flex justify-between items-start mb-4">
                <div>
                    @if ($project->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mb-4">
                            @foreach ($project->tags as $tag)
                                <x-tag :tag="$tag" />
                            @endforeach
                        </div>
                    @endif
                </div>
                <span
                    class="text-sm px-3 py-1 rounded-full
                    @if ($project->status === 'active') bg-green-800/50 text-green-300
                    @elseif($project->status === 'completed') bg-blue-800/50 text-blue-300
                    @else bg-gray-800/50 text-gray-300 @endif">
                    {{ ucfirst($project->status) }}
                </span>
            </div>

            @if ($project->description)
                <div class="prose prose-invert max-w-none">
                    {!! nl2br(e($project->description)) !!}
                </div>
            @else
                <p class="text-white/50 italic">No description provided.</p>
            @endif

            <div class="mt-6 pt-6 border-t border-white/10 text-sm text-white/50">
                Created {{ $project->created_at->diffForHumans() }}
                @if ($project->updated_at->ne($project->created_at))
                    &middot; Updated {{ $project->updated_at->diffForHumans() }}
                @endif
            </div>
        </x-panel>

        {{-- NEW: Related Notes Section --}}
        <div class="mt-8">
            <h3 class="font-bold text-xl mb-4 text-white">Related Notes</h3>

            @if ($project->notes->isEmpty())
                <p class="text-white/50 italic">No notes linked to this project yet.</p>
            @else
                <div class="grid gap-4">
                    @foreach ($project->notes as $note)
                        <a href="/notes/{{ $note->id }}" class="block group">
                            <x-panel class="hover:border-blue-500/50 transition-colors group-hover:bg-white/5">
                                <div class="flex justify-between items-start">
                                    <h4
                                        class="font-bold text-lg text-white group-hover:text-blue-400 transition-colors">
                                        {{ $note->title }}</h4>
                                    <span class="text-xs text-white/50">{{ $note->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="text-white/70 mt-2 line-clamp-2 text-sm">{{ Str::limit($note->body, 100) }}
                                </p>

                                @if ($note->tags->isNotEmpty())
                                    <div class="mt-3 flex gap-2">
                                        @foreach ($note->tags as $tag)
                                            <span
                                                class="text-[10px] px-2 py-1 rounded bg-white/10 text-white/70 border border-white/10">{{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </x-panel>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @can('update', $project)
            <div class="flex justify-between items-center mt-12 pt-6 border-t border-white/10">
                <x-link-button href="/projects/{{ $project->id }}/edit" variant="secondary">Edit Project</x-link-button>

                <form method="POST" action="/projects/{{ $project->id }}"
                    onsubmit="return confirm('Are you sure you want to delete this project?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="bg-red-800/80 hover:bg-red-700 text-white rounded-lg py-2 px-6 font-bold transition-colors">
                        Delete Project
                    </button>
                </form>
            </div>
        @endcan
    </div>
</x-layout>
