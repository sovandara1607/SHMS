@props(['href', 'icon'])

@php
$active = request()->is(ltrim($href, '/') . '*');
@endphp

<a href="{{ $href }}"
   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium {{ $active ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
    <x-icon :name="$icon" class="h-4 w-4 {{ $active ? 'text-white' : 'text-slate-400' }}" />
    {{ $slot }}
</a>
