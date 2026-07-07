@extends('layouts.app')
@section('content')

<x-page-header title="Settings" />

<div class="max-w-lg rounded-xl border border-slate-200 bg-white p-5">
    <p class="mb-4 font-semibold text-slate-900">System Status</p>
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Framework</dt><dd class="text-slate-900">Laravel {{ app()->version() }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Relational DB</dt><dd class="text-slate-900">PostgreSQL ({{ config('database.default') }})</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Cache / Session Driver</dt><dd class="text-slate-900">{{ config('cache.default') }} / {{ config('session.driver') }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Document Store</dt><dd class="text-slate-900">MongoDB ({{ config('database.connections.mongodb.database') }})</dd></div>
    </dl>
    <p class="mt-4 text-xs text-slate-400">Configuration is sourced from the <code>.env</code> file and <code>config/*.php</code>.</p>
</div>
@endsection
