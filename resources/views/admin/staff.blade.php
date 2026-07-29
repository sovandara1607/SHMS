@extends('layouts.app')
@section('content')

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('staff-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'staff-modal' }));
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <div class="mb-5 flex flex-wrap gap-1.5">
        @can('staff.manage')
            <a href="/staff" class="rounded-lg bg-paper-card px-3.5 py-2 text-sm font-medium text-slate-900 shadow-sm">Staff List</a>
        @endcan
        @can('schedule.view')
            <a href="/schedule" class="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">Staff Schedule / Shift</a>
        @endcan
    </div>

    <x-page-header title="Staff Management" :subtitle="$rows->total() . ' staff members registered'">
        <x-slot:actions>
            <a href="/staff/export?{{ http_build_query(['q' => $q, 'role' => $role, 'status' => $status]) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-manila/60 bg-paper-card px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <x-icon name="download" class="h-4 w-4" /> Export
            </a>
            @can('staff.manage')
                <x-button variant="primary" x-on:click="openModal('/staff/create')"><x-icon name="plus" class="h-4 w-4" /> Add Staff</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="get" action="/staff" class="mb-4 shadow-paper rounded-xl border border-manila/60 bg-paper-card p-4">
        <div class="relative mb-3">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by staff ID, name, email, role, or department..."
                   class="w-full rounded-lg border border-manila/60 bg-paper-card py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </div>

        {{-- These two hidden fields must stay above both button rows: a clicked
             button shares its `name` with the other row's preserving hidden
             field, and browsers submit duplicate names in DOM order — PHP's
             $_GET keeps the last one, so the click has to come after. --}}
        <input type="hidden" name="role" value="{{ $role }}">
        <input type="hidden" name="status" value="{{ $status }}">

        <div class="mb-2 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-400">Role:</span>
            @foreach(['' => 'All'] + collect(config('permissions.roles'))->except('super_admin')->all() as $value => $label)
                <button type="submit" name="role" value="{{ $value }}"
                        class="rounded-full px-3 py-1.5 text-sm font-medium {{ (string) $role === (string) $value ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-400">Status:</span>
            @foreach(['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                <button type="submit" name="status" value="{{ $value }}"
                        class="rounded-full px-3 py-1.5 text-sm font-medium {{ (string) $status === (string) $value ? 'bg-blue-600 text-white shadow-well' : 'bg-paper-card text-slate-600 border border-manila/60 shadow-emboss' }}">{{ $label }}</button>
            @endforeach
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-manila/60 bg-paper-card">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="px-4 py-3">Staff ID</th>
                <th class="px-4 py-3">Staff Name</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Department</th>
                <th class="px-4 py-3">Specialization / Unit</th>
                <th class="px-4 py-3">Account Status</th>
                <th class="px-4 py-3">Employment Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                    <td class="px-4 py-3 font-medium text-blue-600">{{ $r->staff_id }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-700">
                                {{ strtoupper(substr($r->first_name, 0, 1) . substr($r->last_name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="font-medium text-slate-900">{{ $r->full_name }}</div>
                                @if($r->title)<div class="text-xs text-slate-400">{{ $r->title }}</div>@endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->email ?: '—' }}</td>
                    <td class="px-4 py-3">@if($r->role)<x-badge :status="$r->role" />@else —@endif</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->department_name ?: '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->specialization_unit ?: '—' }}</td>
                    <td class="px-4 py-3"><x-badge :status="$r->status" /></td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->employment_type ? ucwords(str_replace('_', ' ', $r->employment_type)) : '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end items-center gap-3">
                            <button type="button" x-on:click="openModal('/staff/{{ $r->staff_id }}')" title="View" class="text-slate-400 hover:text-blue-600">
                                <x-icon name="eye" class="h-4 w-4" />
                            </button>
                            @can('staff.manage')
                                <button type="button" x-on:click="openModal('/staff/{{ $r->staff_id }}/edit')" title="Edit" class="text-slate-400 hover:text-blue-600">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>
                                @if($r->status === 'active')
                                    <form method="post" action="/staff/{{ $r->staff_id }}/deactivate" onsubmit="return confirm('Deactivate {{ $r->full_name }}? They will no longer be able to log in.')">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Deactivate</button>
                                    </form>
                                @else
                                    <form method="post" action="/staff/{{ $r->staff_id }}/reactivate">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium text-green-600 hover:underline">Reactivate</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No staff found.</td></tr>
            @endforelse
            </tbody>
        </table>
        <x-pagination :paginator="$rows" />
    </div>

    <x-modal name="staff-modal" max-width="lg">
        <div id="staff-modal-body"></div>
    </x-modal>
</div>
@endsection
