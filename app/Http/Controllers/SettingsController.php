<?php

namespace App\Http\Controllers;

use App\Models\HospitalSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function edit()
    {
        return view('settings.hospital', ['settings' => HospitalSetting::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hospital_name'     => 'nullable|string|max:150',
            'hospital_code'     => 'nullable|string|max:50',
            'license_number'    => 'nullable|string|max:100',
            'address'           => 'nullable|string|max:255',
            'phone_number'      => 'nullable|string|max:50',
            'email'             => 'nullable|email|max:100',
            'website'           => 'nullable|string|max:150',
            'established_year'  => 'nullable|string|max:10',
            'hours_weekday'     => 'nullable|string|max:50',
            'hours_saturday'    => 'nullable|string|max:50',
            'hours_sunday'      => 'nullable|string|max:50',
            'hours_emergency'   => 'nullable|string|max:50',
            'total_beds'        => 'nullable|integer|min:0',
            'icu_beds'          => 'nullable|integer|min:0',
            'emergency_bays'    => 'nullable|integer|min:0',
            'operating_rooms'   => 'nullable|integer|min:0',
        ]);

        $settings = HospitalSetting::current();
        $settings->update($data);
        $this->audit->log('hospital_settings.update', 'hospital_settings', (string) $settings->id);

        return redirect('/hospital-settings')->with('success', 'Settings saved.');
    }
}
