<x-layout>
    <x-page-heading>Log In</x-page-heading>

    <x-forms.form method="POST" action="/login">
        <x-forms.input label="Email" name="email" type="email" placeholder="Enter your email" required />
        <x-forms.input label="Password" name="password" type="password" placeholder="Enter your password" required />

        <x-forms.button>Log In</x-forms.button>

        <p class="text-center text-white/70 mt-4">
            Don't have an account? <a href="/register" class="text-blue-400 hover:underline">Sign up</a>
        </p>
    </x-forms.form>
</x-layout>
