<?php

namespace App\Http\Controllers;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration form.
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Store a new user.
     */
    public function store(Request $request)
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($attributes);

        event(new Registered($user));

        // This job is now handled by the listener for the UserRegistered event
        // SendWelcomeEmail::dispatch($user);

        Auth::login($user);

        return redirect('/')->with('success', 'Welcome! Your account has been created.');
    }
}
