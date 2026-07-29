@props(['type' => 'info'])

@php
$styles = [
    'success' => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'text' => 'text-green-800', 'icon' => 'text-green-500', 'name' => 'check'],
    'error'   => ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'text' => 'text-red-800', 'icon' => 'text-red-500', 'name' => 'alert-triangle'],
    'warning' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-800', 'icon' => 'text-amber-500', 'name' => 'alert-triangle'],
    'info'    => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'text' => 'text-blue-800', 'icon' => 'text-blue-500', 'name' => 'info'],
][$type];
@endphp

<div x-data="{ show: true }" x-show="show"
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="shadow-paper mb-4 flex items-start gap-3 rounded-xl border {{ $styles['border'] }} {{ $styles['bg'] }} px-4 py-3 text-sm {{ $styles['text'] }}">
    <x-icon :name="$styles['name']" class="mt-0.5 h-5 w-5 shrink-0 {{ $styles['icon'] }}" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    <button type="button" x-on:click="show = false" class="shrink-0 rounded p-0.5 {{ $styles['text'] }} opacity-60 hover:opacity-100">
        <x-icon name="x" class="h-4 w-4" />
    </button>
</div>
