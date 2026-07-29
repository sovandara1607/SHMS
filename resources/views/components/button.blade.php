@props(['variant' => 'primary', 'href' => null, 'type' => 'button'])

@php
$variants = [
    'primary' => 'bg-blue-600 text-white hover:bg-blue-500 shadow-emboss',
    'secondary' => 'bg-paper-card text-slate-700 border border-slate-200 hover:bg-slate-50 shadow-emboss',
    'danger' => 'bg-red-600 text-white hover:bg-red-500 shadow-emboss',
    'success' => 'bg-green-600 text-white hover:bg-green-500 shadow-emboss',
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
