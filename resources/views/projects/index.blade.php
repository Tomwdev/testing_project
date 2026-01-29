<x-layout>
    <x-page-heading>My Projects</x-page-heading>

    <x-flash-message />

    <div class="flex justify-between items-center mb-6">
        <p class="text-white/70">Manage your project list</p>
        @auth
            <x-link-button href="/projects/create">Create Project</x-link-button>
        @endauth
    </div>

    @guest
        <x-panel>
            <p class="text-center text-white/70">Please <a href="/login" class="text-blue-400 hover:underline">log in</a> or
                <a href="/register" class="text-blue-400 hover:underline">register</a> to view and manage your projects.</p>
        </x-panel>
    @endguest

    @auth
        @if ($projects->isEmpty())
            <x-panel>
                <p class="text-center text-white/70">You don't have any projects yet. <a href="/projects/create"
                        class="text-blue-400 hover:underline">Create your first project</a>.</p>
            </x-panel>
        @else
            <div class="space-y-4">
                @foreach ($projects as $project)
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
                            @foreach ($project->tags as $tag)
                                <x-tag :tag="$tag" size="small" />
                            @endforeach
                        </x-slot:tags>
                        {{ Str::limit($project->description, 150) }}
                    </x-content-card>
                @endforeach
            </div>
        @endif
    @endauth
</x-layout>
