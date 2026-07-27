<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\CentralServiceClient;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private CentralServiceClient $centralService,
    ) {}

    public function edit()
    {
        $settings = json_decode($this->centralService->getHospitalSettings()->body());

        return view('settings.hospital', ['settings' => $settings]);
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

        $response = $this->centralService->updateHospitalSettings($data);

        if ($response->status() === 422) {
            return back()->withErrors($response->json('errors', []))->withInput();
        }

        $response->throw();

        $settings = $response->json();
        $this->audit->log('hospital_settings.update', 'hospital_settings', (string) $settings['id']);

        return redirect('/hospital-settings')->with('success', 'Settings saved.');
    }
}
