<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PatientManagementTest extends TestCase
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

    private function patientPayload(array $overrides = []): array
    {
        return array_merge([
            'patient_id' => 'PAT0001',
            'first_name' => 'Jane',
            'last_name' => 'Roe',
            'gender' => null,
            'date_of_birth' => null,
            'phone_number' => null,
            'email' => null,
            'address' => null,
            'blood_type' => null,
            'allergy' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'patient_status' => 'active',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'insurance' => [],
            'doctor_assignments' => [],
            'nurse_assignments' => [],
            'adjustments' => [],
        ], $overrides);
    }

    public function test_role_enum_accepts_super_admin_and_rejects_billing_staff(): void
    {
        $admin = $this->makeUser('super_admin');
        $this->assertSame('super_admin', $admin->role);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->makeUser('billing_staff', 'STF0002');
    }

    public function test_admin_can_register_view_edit_and_discharge_a_patient(): void
    {
        $admin = $this->makeUser('admin');

        Http::fake([
            '*/api/patients' => Http::response($this->patientPayload(), 201),
            '*/api/patients/PAT0001' => Http::response($this->patientPayload()),
            '*/api/patients/PAT0001/adjust' => Http::response($this->patientPayload([
                'adjustments' => [[
                    'adjustment_id' => 'PADJ0001', 'patient_id' => 'PAT0001', 'last_name' => 'Doe',
                    'adjusted_by' => 'STF0001', 'adjusted_at' => now()->toIso8601String(), 'reason' => 'Legal name change',
                ]],
            ])),
            '*/api/patients/PAT0001/discharge' => Http::response($this->patientPayload(['patient_status' => 'discharged'])),
            '*/api/appointments*' => Http::response(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 100]]),
            '*/api/medical-records*' => Http::response(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 100]]),
            '*/api/patients/PAT0001/room-assignments' => Http::response([]),
            '*/api/bills*' => Http::response(['bills' => ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 100]], 'payments' => ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 20]], 'stats' => ['total_amount' => 0, 'unpaid' => 0, 'partially_paid' => 0, 'paid' => 0]]),
            '*/api/staff-shifts' => Http::response([]),
            '*/api/patients/PAT0001/release-room' => Http::response(['released' => false]),
        ]);

        $create = $this->actingAs($admin)->post('/patients', [
            'first_name' => 'Jane',
            'last_name' => 'Roe',
            'insurance_provider' => 'BlueCross',
            'policy_number' => 'BC-1',
        ]);
        $create->assertRedirect('/patients');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/patients'
            && $request->method() === 'POST'
            && $request['first_name'] === 'Jane'
            && $request['insurance_provider'] === 'BlueCross');

        $this->actingAs($admin)->get('/patients/PAT0001')->assertOk();

        $this->actingAs($admin)->post('/patients/PAT0001/adjust', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'reason' => 'Legal name change',
        ])->assertRedirect('/patients');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/patients/PAT0001/adjust'
            && $request->method() === 'POST'
            && $request['last_name'] === 'Doe'
            && $request['reason'] === 'Legal name change'
            && $request['adjusted_by'] === 'STF0001');

        $this->actingAs($admin)->post('/patients/PAT0001/discharge')
            ->assertRedirect('/patients');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/patients/PAT0001/discharge'
            && $request->method() === 'POST');
    }

    public function test_receptionist_has_billing_access_but_not_staff_management(): void
    {
        $receptionist = $this->makeUser('receptionist');

        Http::fake([
            '*/api/bills*' => Http::response(['bills' => ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 100]], 'payments' => ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 20]], 'stats' => ['total_amount' => 0, 'unpaid' => 0, 'partially_paid' => 0, 'paid' => 0]]),
        ]);

        $this->actingAs($receptionist)->get('/bills')->assertOk();
        $this->actingAs($receptionist)->get('/staff')->assertForbidden();
        $this->actingAs($receptionist)->get('/departments')->assertForbidden();
    }

    public function test_doctor_assignment_can_be_added_and_ended(): void
    {
        $admin = $this->makeUser('admin');

        $doctorStaff = Staff::create(['staff_id' => 'STF0010', 'first_name' => 'Doc', 'last_name' => 'Tor']);
        Department::create(['department_id' => 'DEP0001', 'department_name' => 'General']);
        $doctor = Doctor::create(['staff_id' => 'STF0010', 'department_id' => 'DEP0001']);

        Http::fake([
            '*/api/patients/PAT0001/doctor-assignments' => Http::response([
                'assignment_id' => 'PDA0001',
                'patient_id' => 'PAT0001',
                'doctor_id' => $doctor->doctor_id,
                'role' => 'main_doctor',
                'status' => 'active',
                'assigned_at' => now()->toIso8601String(),
                'ended_at' => null,
            ], 201),
            '*/api/doctor-assignments/PDA0001/end' => Http::response([
                'assignment_id' => 'PDA0001',
                'patient_id' => 'PAT0001',
                'doctor_id' => $doctor->doctor_id,
                'role' => 'main_doctor',
                'status' => 'completed',
                'assigned_at' => now()->toIso8601String(),
                'ended_at' => now()->toIso8601String(),
            ]),
        ]);

        $this->actingAs($admin)->post('/patients/PAT0001/doctor-assignments', [
            'doctor_id' => $doctor->doctor_id,
            'role' => 'main_doctor',
        ])->assertRedirect('/patients');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/patients/PAT0001/doctor-assignments'
            && $request['doctor_id'] === $doctor->doctor_id
            && $request['assigned_by'] === 'STF0001');

        $this->actingAs($admin)->post('/doctor-assignments/PDA0001/end')
            ->assertRedirect('/patients');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/doctor-assignments/PDA0001/end'
            && $request->method() === 'POST');
    }

    public function test_doctor_view_is_scoped_to_assigned_patients_only(): void
    {
        $doctorStaff = Staff::create(['staff_id' => 'STF0010', 'first_name' => 'Doc', 'last_name' => 'Tor']);
        Department::create(['department_id' => 'DEP0001', 'department_name' => 'General']);
        $doctor = Doctor::create(['staff_id' => 'STF0010', 'department_id' => 'DEP0001']);
        $doctorUser = User::create([
            'staff_id' => 'STF0010',
            'email' => 'doc@test.local',
            'password_hash' => Hash::make('secret'),
            'role' => 'doctor',
        ]);

        Http::fake([
            '*/api/patients?*' => Http::response(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 20]]),
            '*/api/patients/PAT0001' => Http::response($this->patientPayload(['doctor_assignments' => []])),
            '*/api/appointments*' => Http::response(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 100]]),
            '*/api/medical-records*' => Http::response(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 100]]),
            '*/api/patients/PAT0001/room-assignments' => Http::response([]),
            '*/api/bills*' => Http::response(['bills' => ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 100]], 'payments' => ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 20]], 'stats' => ['total_amount' => 0, 'unpaid' => 0, 'partially_paid' => 0, 'paid' => 0]]),
        ]);

        $this->actingAs($doctorUser)->get('/patients')->assertOk();
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://127.0.0.1:8100/api/patients?')
            && $request['assigned_doctor_id'] === $doctor->doctor_id);

        $this->actingAs($doctorUser)->get('/patients/PAT0001')->assertForbidden();
    }
}
