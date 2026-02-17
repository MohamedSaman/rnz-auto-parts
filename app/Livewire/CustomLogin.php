<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.guest')]
class CustomLogin extends Component
{
    public $email = '';
    public $password = '';
    public $remember = false;

    public function render()
    {
        return view('livewire.custom-login');
    }

    public function login()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();

            $user = Auth::user();

            if ($user && $user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } else if ($user && $user->role === 'staff') {
                // Let the main dashboard route handle staff type redirects
                return redirect()->route('dashboard');
            } else {
                return redirect()->route('dashboard');
            }
        }

        // When authentication fails, add an error to Livewire's error bag
        $this->addError('email', 'These credentials do not match our records.');
        // Clear the password field for security and UX
        $this->password = '';
    }

    /**
     * Clear validation/error for a property when it is updated.
     * This helps remove the red border / shaking animation as soon as user starts typing.
     */
}
