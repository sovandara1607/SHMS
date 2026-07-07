<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private OtpService $otp,
        private AuditLogger $audit,
    ) {}

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

    public function showForgot()
    {
        return view('auth.forgot');
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        // Behave the same whether or not the account exists (no enumeration).
        if (User::where('email', $data['email'])->where('status', 'active')->exists()) {
            $this->otp->issue($data['email']);
            $request->session()->put('reset_email', $data['email']);
        }
        return redirect('/reset-password')
            ->with('success', 'If the account exists, a reset code was sent. (Demo: see storage/logs/otp.log)');
    }

    public function showReset(Request $request)
    {
        return view('auth.reset', ['email' => $request->session()->get('reset_email', '')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'otp'      => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (! $this->otp->verify($data['email'], $data['otp'])) {
            return back()->withErrors(['otp' => 'Invalid or expired reset code.'])->onlyInput('email');
        }

        $user = User::where('email', $data['email'])->first();
        if ($user) {
            $user->password_hash = Hash::make($data['password']);
            $user->save();
            $this->otp->consume($data['email']);
            $this->audit->log('auth.password_reset', 'users', $user->user_id);
        }
        $request->session()->forget('reset_email');
        return redirect('/login')->with('success', 'Password updated. Please log in.');
    }
}
