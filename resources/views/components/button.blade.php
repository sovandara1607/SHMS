@props(['variant' => 'primary', 'href' => null, 'type' => 'button'])

@php
$variants = [
    'primary' => 'bg-blue-600 text-white hover:bg-blue-700',
    'secondary' => 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50',
    'danger' => 'bg-red-600 text-white hover:bg-red-700',
    'success' => 'bg-green-600 text-white hover:bg-green-700',
    'ghost' => 'text-slate-500 hover:text-slate-700',
];
$classes = 'inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold transition ' . ($variants[$variant] ?? $variants['primary']);
$tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    {{ $attributes->merge(array_filter([
        'class' => $classes,
        'href' => $href,
        'type' => $href ? null : $type,
    ])) }}
>{{ $slot }}</{{ $tag }}>
