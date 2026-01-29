@if (session('success'))
    <div class="bg-green-800/50 border border-green-600 text-green-200 px-4 py-3 rounded-xl mb-6">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="bg-red-800/50 border border-red-600 text-red-200 px-4 py-3 rounded-xl mb-6">
        {{ session('error') }}
    </div>
@endif
