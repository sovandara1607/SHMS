@extends('layouts.app')
@section('content')

<x-page-header title="My Profile" />

<div class="max-w-lg rounded-xl border border-slate-200 bg-white p-5">
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Name</dt><dd class="font-medium text-slate-900">{{ $profile->first_name ?? '' }} {{ $profile->last_name ?? '' }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Staff ID</dt><dd class="text-slate-900">{{ $profile->staff_id ?? '' }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="text-slate-900">{{ $profile->email ?? '' }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Role</dt><dd class="text-slate-900">{{ config('permissions.roles.' . ($profile->role ?? ''), $profile->role ?? '') }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd class="text-slate-900">{{ $profile->phone_number ?? '—' }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Hire Date</dt><dd class="text-slate-900">{{ $profile->hire_date ?? '—' }}</dd></div>
    </dl>
    <p class="mt-4 text-xs text-slate-400">To change your password use the "Forgot password" flow from the login screen.</p>
</div>
@endsection
