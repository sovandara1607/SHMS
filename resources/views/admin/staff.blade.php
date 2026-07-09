@extends('layouts.app')
@section('content')

<div x-data="{
        async openModal(url) {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const body = document.getElementById('staff-modal-body');
            body.innerHTML = await res.text();
            window.Alpine.initTree(body);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'staff-modal' }));
        },
        roleFilter: 'all',
        statusFilter: 'all',
        selected: [],
        rows: @js($rows->map(fn ($r) => ['id' => $r->staff_id, 'role' => $r->role, 'status' => $r->status])),
        get visibleIds() {
            return this.rows
                .filter(r => (this.roleFilter === 'all' || r.role === this.roleFilter) && (this.statusFilter === 'all' || r.status === this.statusFilter))
                .map(r => r.id);
        },
        get allVisibleSelected() {
            return this.visibleIds.length > 0 && this.visibleIds.every(id => this.selected.includes(id));
        },
        toggleAll() {
            this.selected = this.allVisibleSelected
                ? this.selected.filter(id => !this.visibleIds.includes(id))
                : [...new Set([...this.selected, ...this.visibleIds])];
        },
        toggleRow(id) {
            this.selected = this.selected.includes(id) ? this.selected.filter(x => x !== id) : [...this.selected, id];
        },
        rowVisible(role, status) {
            return (this.roleFilter === 'all' || role === this.roleFilter) && (this.statusFilter === 'all' || status === this.statusFilter);
        },
        async deactivateSelected() {
            if (! confirm(`Deactivate ${this.selected.length} staff member(s)? They will no longer be able to log in.`)) return;
            const token = document.querySelector('meta[name=csrf-token]').content;
            for (const id of this.selected) {
                await fetch(`/staff/${id}/deactivate`, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });
            }
            window.location.reload();
        }
     }"
     x-init="@if($errors->any() && old('_modal_target'))openModal(@js(old('_modal_target')))@endif"
>
    <x-page-header title="Staff Management">
        <x-slot:actions>
            @can('staff.manage')
                <x-button variant="primary" x-on:click="openModal('/staff/create')"><x-icon name="plus" class="h-4 w-4" /> Add Staff</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <form method="get" action="/staff" class="relative max-w-md flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or staff ID..."
                   class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
        </form>

        <select x-model="roleFilter" class="rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            <option value="all">All Roles</option>
            @foreach(config('permissions.roles') as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>

        <select x-model="statusFilter" class="rounded-lg border border-slate-200 bg-white py-2.5 px-3 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>

        @can('staff.manage')
            <button type="button" x-show="selected.length > 0" x-on:click="deactivateSelected()"
                    class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm font-medium text-red-600 hover:bg-red-100"
                    style="display: none;">
                Deactivate Selected (<span x-text="selected.length"></span>)
            </button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                <th class="w-10 px-4 py-3">
                    <input type="checkbox" :checked="allVisibleSelected" x-on:change="toggleAll()" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                </th>
                <th class="px-4 py-3">ID</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Email</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr class="border-b border-slate-50 last:border-0" x-show="rowVisible({{ Js::from($r->role) }}, {{ Js::from($r->status) }})">
                    <td class="px-4 py-3">
                        <input type="checkbox" :checked="selected.includes({{ Js::from($r->staff_id) }})" x-on:change="toggleRow({{ Js::from($r->staff_id) }})" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    </td>
                    <td class="px-4 py-3 font-medium text-blue-600">{{ $r->staff_id }}</td>
                    <td class="px-4 py-3 text-slate-900">{{ $r->full_name }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->role ? ucwords(str_replace('_', ' ', $r->role)) : '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $r->email ?: '—' }}</td>
                    <td class="px-4 py-3"><x-badge :status="$r->status" /></td>
                    <td class="px-4 py-3 text-right">
                        @can('staff.manage')
                            <x-row-actions>
                                <button type="button" x-on:click="openModal('/staff/{{ $r->staff_id }}/edit')" class="block w-full px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50">Edit</button>
                                @if($r->status === 'active')
                                    <form method="post" action="/staff/{{ $r->staff_id }}/deactivate" onsubmit="return confirm('Deactivate {{ $r->full_name }}? They will no longer be able to log in.')">
                                        @csrf
                                        <button type="submit" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50">Deactivate</button>
                                    </form>
                                @else
                                    <form method="post" action="/staff/{{ $r->staff_id }}/reactivate">
                                        @csrf
                                        <button type="submit" class="block w-full px-3 py-1.5 text-left text-sm text-green-600 hover:bg-green-50">Reactivate</button>
                                    </form>
                                @endif
                            </x-row-actions>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No staff found.</td></tr>
            @endforelse
            </tbody>
            @if($rows->count())
                <tfoot class="border-t border-slate-100 bg-slate-50/60">
                    <tr>
                        <td colspan="6" class="px-4 py-2.5 text-sm font-medium text-slate-600">Total Staff</td>
                        <td class="px-4 py-2.5 text-right text-sm font-semibold text-slate-900">{{ $rows->count() }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <x-modal name="staff-modal" max-width="lg">
        <div id="staff-modal-body"></div>
    </x-modal>
</div>
@endsection
