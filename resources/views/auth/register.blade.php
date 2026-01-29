<x-layout>
    <x-page-heading>Register</x-page-heading>

    <x-forms.form method="POST" action="/register">
        <x-forms.input label="Name" name="name" placeholder="Enter your name" required />
        <x-forms.input label="Email" name="email" type="email" placeholder="Enter your email" required />
        <x-forms.input label="Password" name="password" type="password" placeholder="Create a password" required />
        <x-forms.input label="Confirm Password" name="password_confirmation" type="password"
            placeholder="Confirm your password" required />

        <x-forms.button>Create Account</x-forms.button>

        <p class="text-center text-white/70 mt-4">
            Already have an account? <a href="/login" class="text-blue-400 hover:underline">Log in</a>
        </p>
    </x-forms.form>
</x-layout>
