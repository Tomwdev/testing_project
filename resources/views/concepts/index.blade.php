<x-layout>
    <x-page-heading>My Concepts</x-page-heading>

    <x-flash-message />

    <div class="flex justify-between items-center mb-6">
        <p class="text-white/70">Track your learning concepts and ideas</p>
        @auth
            <x-link-button href="/concepts/create">Create Concept</x-link-button>
        @endauth
    </div>

    @guest
        <x-panel>
            <p class="text-center text-white/70">Please <a href="/login" class="text-blue-400 hover:underline">log in</a> or
                <a href="/register" class="text-blue-400 hover:underline">register</a> to view and manage your concepts.</p>
        </x-panel>
    @endguest

    @auth
        @if ($concepts->isEmpty())
            <x-panel>
                <p class="text-center text-white/70">You don't have any concepts yet. <a href="/concepts/create"
                        class="text-blue-400 hover:underline">Create your first concept</a>.</p>
            </x-panel>
        @else
            <div class="space-y-4">
                @foreach ($concepts as $concept)
                    <x-content-card :title="$concept->title" :href="'/concepts/' . $concept->id" type="concept">
                        <x-slot:tags>
                            @foreach ($concept->tags as $tag)
                                <x-tag :tag="$tag" size="small" />
                            @endforeach
                        </x-slot:tags>
                        {{ Str::limit($concept->description, 150) }}
                    </x-content-card>
                @endforeach
            </div>
        @endif
    @endauth
</x-layout>
