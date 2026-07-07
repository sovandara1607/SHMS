@extends('layouts.app')
@section('content')

@php
$roleLabel = config('permissions.roles.' . auth()->user()->role, ucfirst($role));
@endphp

<div class="mb-5 rounded-xl hero-gradient px-6 py-5 text-white">
    <p class="text-xs uppercase tracking-wider text-blue-100">Welcome back</p>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold">{{ $roleLabel }}</h1>
            <p class="text-sm text-blue-100">{{ $roleLabel }} Dashboard</p>
        </div>
        <p class="text-right text-sm text-blue-100">{{ now()->format('l, F j') }}<br>{{ now()->format('Y') }}</p>
    </div>
</div>

@include('dashboard._' . $role)
@endsection
