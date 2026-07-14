@extends('layouts.app')
@section('content')

<x-page-header :title="$title" :subtitle="$intro ?? null" />

@if(!empty($searchAction))
    <form method="get" action="{{ $searchAction }}" class="relative mb-4 max-w-md">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Search..."
               class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
    </form>
@endif

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wider text-slate-400">
                @foreach($columns as $label)<th class="px-4 py-3">{{ $label }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
            @php($r = (array) $r)
            <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50">
                @foreach(array_keys($columns) as $k)
                    <td class="px-4 py-3 text-slate-700">{{ is_numeric($r[$k] ?? null) ? (is_float($r[$k]) ? number_format($r[$k], 2) : $r[$k]) : ($r[$k] ?? '—') }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="px-4 py-8 text-center text-slate-400">No records found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
