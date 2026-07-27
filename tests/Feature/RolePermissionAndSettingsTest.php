<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RolePermissionAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $staffId = 'STF0001'): User
    {
        Staff::create(['staff_id' => $staffId, 'first_name' => 'Test', 'last_name' => ucfirst($role)]);

        return User::create([
            'staff_id' => $staffId,
            'email' => "$role@test.local",
            'password_hash' => Hash::make('secret'),
            'role' => $role,
        ]);
    }

    public function test_admin_can_view_and_update_a_non_protected_role(): void
    {
        $admin = $this->makeUser('admin');
        $doctor = $this->makeUser('doctor', 'STF0002');

        $this->actingAs($admin)->get('/roles-permissions')->assertOk();

        $this->assertFalse($doctor->hasPermission('bill.view'));

        $this->actingAs($admin)->post('/roles-permissions/doctor', [
            'capabilities' => ['patient.view', 'bill.view'],
        ])->assertRedirect('/roles-permissions');

        $this->assertEqualsCanonicalizing(['patient.view', 'bill.view'], RolePermission::where('role', 'doctor')->pluck('capability')->all());
        $this->assertTrue($doctor->fresh()->hasPermission('bill.view'));
        $this->assertTrue($doctor->fresh()->hasPermission('dashboard.view'), 'baseline capability should always be granted');
    }

    public function test_protected_roles_cannot_be_edited(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post('/roles-permissions/super_admin', [
            'capabilities' => ['patient.view'],
        ])->assertForbidden();

        $this->actingAs($admin)->post('/roles-permissions/admin', [
            'capabilities' => ['patient.view'],
        ])->assertForbidden();
    }

    public function test_admin_can_update_hospital_settings(): void
    {
        $admin = $this->makeUser('admin');

        Http::fake([
            '*/api/hospital-settings' => Http::response([
                'id' => 1,
                'hospital_name' => 'Test General Hospital',
                'hospital_code' => null,
                'license_number' => null,
                'address' => null,
                'phone_number' => null,
                'email' => null,
                'website' => null,
                'established_year' => null,
                'hours_weekday' => null,
                'hours_saturday' => null,
                'hours_sunday' => null,
                'hours_emergency' => null,
                'total_beds' => 100,
                'icu_beds' => null,
                'emergency_bays' => null,
                'operating_rooms' => null,
            ]),
        ]);

        $this->actingAs($admin)->get('/hospital-settings')->assertOk();

        $this->actingAs($admin)->put('/hospital-settings', [
            'hospital_name' => 'Test General Hospital',
            'total_beds' => 100,
        ])->assertRedirect('/hospital-settings');

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/hospital-settings'
            && $request->method() === 'PUT'
            && $request['hospital_name'] === 'Test General Hospital'
            && $request['total_beds'] === 100);
    }

    public function test_every_role_dashboard_renders(): void
    {
        foreach (['super_admin', 'admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician'] as $i => $role) {
            $user = $this->makeUser($role, 'STF' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT));
            $this->actingAs($user)->get('/dashboard')->assertOk();
        }
    }
}
