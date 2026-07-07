@extends('layouts.app')
@section('content')

<x-page-header title="Settings" />

<form method="post" action="/hospital-settings">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="mb-4 font-semibold text-slate-900">Hospital Information</p>
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Hospital Name</label>
                    <input name="hospital_name" value="{{ old('hospital_name', $settings->hospital_name) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Hospital Code / ID</label>
                        <input name="hospital_code" value="{{ old('hospital_code', $settings->hospital_code) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">License Number</label>
                        <input name="license_number" value="{{ old('license_number', $settings->license_number) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Address</label>
                    <input name="address" value="{{ old('address', $settings->address) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Phone Number</label>
                        <input name="phone_number" value="{{ old('phone_number', $settings->phone_number) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $settings->email) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Website</label>
                        <input name="website" value="{{ old('website', $settings->website) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Established Year</label>
                        <input name="established_year" value="{{ old('established_year', $settings->established_year) }}" class="w-full rounded-lg border border-slate-200 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="mb-4 font-semibold text-slate-900">Operating Hours</p>
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-slate-600">Monday - Friday</label>
                        <input name="hours_weekday" value="{{ old('hours_weekday', $settings->hours_weekday) }}" class="w-56 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-slate-600">Saturday</label>
                        <input name="hours_saturday" value="{{ old('hours_saturday', $settings->hours_saturday) }}" class="w-56 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-slate-600">Sunday</label>
                        <input name="hours_sunday" value="{{ old('hours_sunday', $settings->hours_sunday) }}" class="w-56 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-slate-600">Emergency</label>
                        <input name="hours_emergency" value="{{ old('hours_emergency', $settings->hours_emergency) }}" class="w-56 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="mb-4 font-semibold text-slate-900">Department Capacity</p>
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-slate-600">Total Beds</label>
                        <input type="number" name="total_beds" value="{{ old('total_beds', $settings->total_beds) }}" class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-slate-600">ICU Beds</label>
                        <input type="number" name="icu_beds" value="{{ old('icu_beds', $settings->icu_beds) }}" class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-slate-600">Emergency Bays</label>
                        <input type="number" name="emergency_bays" value="{{ old('emergency_bays', $settings->emergency_bays) }}" class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm text-slate-600">Operating Rooms</label>
                        <input type="number" name="operating_rooms" value="{{ old('operating_rooms', $settings->operating_rooms) }}" class="w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 flex justify-end">
        <x-button variant="primary" type="submit">Save Settings</x-button>
    </div>
</form>
@endsection
