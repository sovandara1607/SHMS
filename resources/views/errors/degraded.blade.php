@extends('layouts.auth')
@section('content')
<div class="flex min-h-screen items-center justify-center px-6">
    <div class="w-full max-w-sm text-center">
        <p class="text-5xl font-bold text-slate-900">503</p>
        <p class="mt-2 text-sm text-slate-500">The system is temporarily unavailable. Please try again shortly. Your data is safe.</p>
        <a href="/dashboard" class="mt-6 inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
            Try again
        </a>
    </div>
</div>
@endsection
