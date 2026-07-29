@extends('layouts.auth')
@section('content')
<div class="grid min-h-screen grid-cols-1 lg:grid-cols-2">
    {{-- Brand panel: the same clipboard signature as the app shell — a
         steel clip gripping a paper chart cover, instead of the old flat
         blue-gradient hero. --}}
    <div class="texture-paper relative hidden overflow-hidden border-r border-slate-200 bg-paper-card lg:flex lg:flex-col lg:items-center lg:justify-center lg:px-16">
        <div class="absolute inset-x-16 top-10 h-3">
            <div class="clip-steel absolute inset-x-10 top-0 h-3 rounded-b-md"></div>
            <div class="clip-steel absolute left-1/2 top-0 h-5 w-9 -translate-x-1/2 rounded-b-lg"></div>
        </div>

        <div class="shadow-paper relative z-10 flex flex-col items-center rounded-2xl border border-slate-200 bg-paper-card px-12 py-14 text-center">
            <div class="shadow-emboss mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2 5 4-14 2 9h6" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Smart Hospital Management</h1>
            <p class="mt-3 max-w-xs text-sm text-slate-500">
                A comprehensive system for managing patients, staff, appointments, and hospital operations with ease.
            </p>
        </div>
    </div>

    {{-- Form panel --}}
    <div class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm" x-data="{ showPassword: false }">
            <div class="mb-8 flex items-center gap-3">
                <div class="shadow-emboss flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2 5 4-14 2 9h6" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold leading-tight text-slate-900">Smart Hospital</div>
                    <div class="text-xs leading-tight text-slate-400">Management System</div>
                </div>
            </div>

            <h2 class="text-xl font-bold text-slate-900">Welcome back</h2>
            <p class="mt-1 text-sm text-slate-500">Choose your role and sign in</p>

            @if(session('success'))
                <x-alert type="success">{{ session('success') }}</x-alert>
            @endif
            @if($errors->any())
                <x-alert type="error">{{ $errors->first() }}</x-alert>
            @endif

            <form method="post" action="/login" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           placeholder="you@hospital.test"
                           class="shadow-well w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:bg-paper-card focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    </div>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required
                               placeholder="••••••••"
                               class="shadow-well w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 pr-10 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:bg-paper-card focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    Remember me for 30 days
                </label>

                <button type="submit"
                        class="shadow-emboss w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">
                    Sign In
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-slate-400">Smart Hospital Management System · HIPAA Compliant</p>
        </div>
    </div>
</div>
@endsection
