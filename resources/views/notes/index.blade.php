<x-layout>
    <x-page-heading>My Notes</x-page-heading>

    <x-flash-message />

    <div class="flex justify-between items-center mb-6">
        <p class="text-white/70">Manage your personal notes</p>
        @auth
            <x-link-button href="/notes/create">Create Note</x-link-button>
        @endauth
    </div>

    @guest
        <x-panel>
            <p class="text-center text-white/70">Please <a href="/login" class="text-blue-400 hover:underline">log in</a> or
                <a href="/register" class="text-blue-400 hover:underline">register</a> to view and manage your notes.</p>
        </x-panel>
    @endguest

    @auth
        @if ($notes->isEmpty())
            <x-panel>
                <p class="text-center text-white/70">You don't have any notes yet. <a href="/notes/create"
                        class="text-blue-400 hover:underline">Create your first note</a>.</p>
            </x-panel>
        @else
            <div class="space-y-4">
                @foreach ($notes as $note)
                    <x-content-card :title="$note->title" :href="'/notes/' . $note->id" type="note">
                        <x-slot:tags>
                            @foreach ($note->tags as $tag)
                                <x-tag :tag="$tag" size="small" />
                            @endforeach
                        </x-slot:tags>
                        {{ Str::limit($note->body, 150) }}
                    </x-content-card>
                @endforeach
            </div>
        @endif
    @endauth
</x-layout>
