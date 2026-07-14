<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function showLogin()
    {
        return Auth::check() ? redirect('/dashboard') : view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // status=active is added as a query constraint; password is hash-checked.
        if (Auth::attempt(['email' => $data['email'], 'status' => 'active', 'password' => $data['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $this->audit->log('auth.login', 'users', Auth::user()->user_id);
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $id = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($id) {
            $this->audit->log('auth.logout', 'users', $id);
        }
        return redirect('/login');
    }
}
