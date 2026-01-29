<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Notes App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/js/app.js', 'resources/css/app.css'])
</head>

<body class="bg-black text-white font-hanken-grotesk pb-20">
    <div class="px-10">
        <nav class="flex justify-between items-center py-4 border-b border-white/10">
            <div>
                <a href="/">
                    <img src="{{ Vite::asset('resources/images/logo.svg') }}" alt="Notes App" class="w-12 h-12">
                </a>
            </div>
            <div class="space-x-6 font-bold">
                <a href="/"
                    class="{{ request()->is('/') ? 'text-white' : 'text-white/70 hover:text-white' }}
                    transition-colors">Home</a>
                <a href="/notes"
                    class="{{ request()->is('notes*') ? 'text-white' : 'text-white/70 hover:text-white' }} transition-colors">Notes</a>
                <a href="/projects"
                    class="{{ request()->is('projects*') ? 'text-white' : 'text-white/70 hover:text-white' }} transition-colors">Projects</a>
                <a href="/concepts"
                    class="{{ request()->is('concepts*') ? 'text-white' : 'text-white/70 hover:text-white' }} transition-colors">Concepts</a>
            </div>

            @auth
                <div class="space-x-6 font-bold flex items-center">
                    <span class="text-white/50 text-sm">{{ auth()->user()->name }}</span>

                    <form method="POST" action="/logout">
                        @csrf
                        <button class="text-white/70 hover:text-white transition-colors">Log Out</button>
                    </form>
                </div>
            @endauth

            @guest
                <div class="space-x-6 font-bold">
                    <a href="/register" class="text-white/70 hover:text-white transition-colors">Sign Up</a>
                    <a href="/login" class="text-white/70 hover:text-white transition-colors">Log In</a>
                </div>
            @endguest
        </nav>

        <main class="mt-10 max-w-[986px] mx-auto">
            {{ $slot }}
        </main>
    </div>

</body>

</html>
