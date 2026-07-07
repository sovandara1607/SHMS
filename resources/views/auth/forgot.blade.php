@extends('layouts.auth')
@section('content')
<div class="flex min-h-screen items-center justify-center px-6 py-12">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2 5 4-14 2 9h6" />
                </svg>
            </div>
            <div>
                <div class="text-sm font-bold leading-tight text-slate-900">Smart Hospital</div>
                <div class="text-xs leading-tight text-slate-400">Management System</div>
            </div>
        </div>

        <h2 class="text-xl font-bold text-slate-900">Forgot password</h2>
        <p class="mt-1 text-sm text-slate-500">Enter your staff email to receive a one-time reset code.</p>

        @if($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="/forgot-password" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                Send reset code
            </button>
        </form>
        <p class="mt-4 text-center text-sm"><a href="/login" class="font-medium text-blue-600 hover:text-blue-700">Back to sign in</a></p>
    </div>
</div>
@endsection
