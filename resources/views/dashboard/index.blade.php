@extends('layouts.app')
@section('content')

@php
$roleLabel = config('permissions.roles.' . auth()->user()->role, ucfirst($role));
@endphp

{{-- Chart cover page: paper card matching the rest of the app, with a
     rotated red ink-stamp date — the way an admission form gets stamped —
     instead of the old flat blue-gradient hero banner. --}}
<div class="shadow-paper mb-5 flex items-center justify-between gap-4 rounded-xl border border-manila bg-paper-card px-6 py-5">
    <div>
        <p class="text-xs uppercase tracking-wider text-slate-400">Welcome back</p>
        <h1 class="text-xl font-bold text-slate-900">{{ $roleLabel }}</h1>
        <p class="text-sm text-slate-500">{{ $roleLabel }} Dashboard</p>
    </div>
    <div class="shrink-0 -rotate-6 rounded border-2 border-red-600/70 px-3 py-1.5 text-center font-mono text-[11px] font-bold uppercase leading-tight tracking-wider text-red-600/80"
         style="mix-blend-mode: multiply;">
        {{ now()->format('l') }}<br>{{ now()->format('M j, Y') }}
    </div>
</div>

@include('dashboard._' . $role)
@endsection
