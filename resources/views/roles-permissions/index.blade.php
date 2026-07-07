@extends('layouts.app')
@section('content')

<div x-data="{
        selected: '{{ $selected }}',
        async selectRole(role) {
            this.selected = role;
            const res = await fetch('/roles-permissions/' + role, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const container = document.getElementById('permissions-panel-container');
            container.innerHTML = await res.text();
            window.Alpine.initTree(container);
        }
     }"
>
    <x-page-header title="Roles &amp; Permissions" />

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[280px_1fr]">
        <div class="rounded-xl border border-slate-200 bg-white p-3">
            <p class="mb-2 px-2 text-xs font-semibold uppercase tracking-wider text-slate-400">System Roles</p>
            <p class="mb-3 px-2 text-xs text-slate-400">{{ count($roles) }} roles defined</p>
            <div class="space-y-1">
                @foreach($roles as $role => $label)
                    <button type="button" @click="selectRole('{{ $role }}')"
                            :class="selected === '{{ $role }}' ? 'bg-blue-50 border-blue-200' : 'border-transparent hover:bg-slate-50'"
                            class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left">
                        <span>
                            <span class="block text-sm font-medium text-slate-900">{{ $label }}</span>
                            <span class="block text-xs text-slate-400">
                                @if(in_array($role, ['super_admin', 'admin'], true)) Full system access
                                @else {{ \Illuminate\Support\Str::headline($role) }} @endif
                            </span>
                        </span>
                        @if($role === 'super_admin')
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-600">Protected</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <div id="permissions-panel-container">
            @include('roles-permissions.panel')
        </div>
    </div>
</div>
@endsection
