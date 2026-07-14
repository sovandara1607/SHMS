<?php $u = auth()->user(); $roleLabel = config('permissions.roles.' . ($u->role ?? ''), $u->role ?? ''); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Legacy stylesheet kept as a fallback for views not yet migrated to Tailwind; remove once Phase 3 is complete. --}}
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
<x-loading-overlay />
<div class="flex min-h-screen"
     x-data="{ sidebarOpen: JSON.parse(localStorage.getItem('sh_sidebar_open') ?? (window.innerWidth >= 1024 ? 'true' : 'false')) }"
     x-init="$watch('sidebarOpen', (v) => localStorage.setItem('sh_sidebar_open', JSON.stringify(v)))">
    {{-- Below the lg breakpoint the sidebar becomes an overlay (fixed +
         slide-in) instead of squeezing the content column; this backdrop
         only exists to close it on an outside tap. --}}
    <div x-show="sidebarOpen" x-on:click="sidebarOpen = false" x-transition.opacity
         class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" style="display:none" aria-hidden="true"></div>

    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col overflow-hidden border-slate-200 bg-white transition-transform duration-200 lg:static lg:z-auto lg:transition-[width,border-width]"
           :class="sidebarOpen ? 'translate-x-0 lg:w-64 lg:border-r' : '-translate-x-full lg:translate-x-0 lg:w-0 lg:border-r-0'">
        <div class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-4">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2 5 4-14 2 9h6" />
                </svg>
            </div>
            <div>
                <div class="text-sm font-bold leading-tight text-slate-900">Smart Hospital</div>
                <div class="text-xs leading-tight text-slate-400">Management System</div>
            </div>
        </div>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
            <div>
                <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Overview</p>
                @can('dashboard.view')
                    <x-nav-link href="/dashboard" icon="grid">Dashboard</x-nav-link>
                @endcan
            </div>

            @if($u->hasPermission('patient.view') || $u->hasPermission('appointment.view') || $u->hasPermission('medical_record.view') || $u->hasPermission('treatment.view'))
                <div>
                    <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Patient Care</p>
                    @can('patient.view')<x-nav-link href="/patients" icon="users">Patients</x-nav-link>@endcan
                    @can('appointment.view')<x-nav-link href="/appointments" icon="calendar">Appointments</x-nav-link>@endcan
                    @can('medical_record.view')<x-nav-link href="/medical-records" icon="document">Medical Records</x-nav-link>@endcan
                    @can('treatment.view')<x-nav-link href="/treatments" icon="clipboard">Treatments</x-nav-link>@endcan
                </div>
            @endif

            @if($u->hasPermission('staff.manage') || $u->hasPermission('room.view'))
                <div>
                    <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Hospital</p>
                    @can('staff.manage')
                        <x-nav-link href="/staff" icon="users">Staff</x-nav-link>
                        <x-nav-link href="/departments" icon="building">Departments</x-nav-link>
                    @endcan
                    @can('room.view')<x-nav-link href="/rooms" icon="bed">Rooms &amp; Beds</x-nav-link>@endcan
                </div>
            @endif

            @if($u->hasPermission('medicine.view') || $u->hasPermission('lab_order.view') || $u->hasPermission('lab_result.view') || $u->hasPermission('bill.view'))
                <div>
                    <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Services</p>
                    @can('medicine.view')<x-nav-link href="/medicines" icon="pill">Pharmacy</x-nav-link>@endcan
                    @if($u->hasPermission('lab_order.view') || $u->hasPermission('lab_result.view'))
                        <x-nav-link href="/lab-orders" icon="flask">Laboratory</x-nav-link>
                    @endif
                    @can('bill.view')<x-nav-link href="/bills" icon="card">Billing &amp; Payments</x-nav-link>@endcan
                </div>
            @endif

            @if(\Illuminate\Support\Facades\Route::has('roles-permissions.index') && ($u->role === 'super_admin' || $u->role === 'admin'))
                <div>
                    <p class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Administration</p>
                    <x-nav-link href="/roles-permissions" icon="shield">Roles &amp; Permissions</x-nav-link>
                    @if(\Illuminate\Support\Facades\Route::has('settings.hospital'))
                        <x-nav-link href="/hospital-settings" icon="cog">Settings</x-nav-link>
                    @endif
                </div>
            @endif
        </nav>

        <div class="border-t border-slate-100 p-3">
            <a href="/profile" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                <x-icon name="users" class="h-4 w-4" /> Profile
            </a>
            <a href="/settings" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">
                <x-icon name="cog" class="h-4 w-4" /> Settings
            </a>
            <form action="/logout" method="post">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                    <x-icon name="logout" class="h-4 w-4" /> Logout
                </button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 sm:px-6">
            <button type="button" x-on:click="sidebarOpen = !sidebarOpen"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                    :aria-expanded="sidebarOpen" aria-label="Toggle sidebar">
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            <div class="relative" x-data="{ open: false }">
                <button type="button" x-on:click="open = !open" x-on:click.outside="open = false" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
                        {{ strtoupper(substr($roleLabel, 0, 1)) }}
                    </div>
                    <div class="hidden max-w-[160px] text-left sm:block">
                        <div class="truncate text-sm font-medium leading-tight text-slate-900">{{ $roleLabel }}</div>
                        <div class="truncate text-xs leading-tight text-slate-400">{{ $u->email }}</div>
                    </div>
                    <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400" />
                </button>
                <div x-show="open" x-transition style="display:none" class="absolute right-0 z-20 mt-2 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    <a href="/profile" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Profile</a>
                    <a href="/settings" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Settings</a>
                    <form action="/logout" method="post">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">Logout</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6">
            @if(session('success'))
                <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    Please fix the following:
                    <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
