<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

        $create = $this->actingAs($admin)->post('/patients', [
            'first_name' => 'Jane',
            'last_name' => 'Roe',
            'insurance_provider' => 'BlueCross',
            'policy_number' => 'BC-1',
        ]);
        $create->assertRedirect('/patients');

        $patient = Patient::first();
        $this->assertNotNull($patient);
        $this->assertSame('Jane', $patient->first_name);
        $this->assertSame(1, $patient->insurance()->count());

        $this->actingAs($admin)->get("/patients/{$patient->patient_id}")->assertOk();

        $this->actingAs($admin)->put("/patients/{$patient->patient_id}", [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ])->assertRedirect('/patients');
        $this->assertSame('Doe', $patient->fresh()->last_name);

        $this->actingAs($admin)->post("/patients/{$patient->patient_id}/discharge")
            ->assertRedirect('/patients');
        $this->assertSame('discharged', $patient->fresh()->patient_status);
    }

    public function test_receptionist_has_billing_access_but_not_staff_management(): void
    {
        $receptionist = $this->makeUser('receptionist');

        $this->actingAs($receptionist)->get('/bills')->assertOk();
        $this->actingAs($receptionist)->get('/staff')->assertForbidden();
        $this->actingAs($receptionist)->get('/departments')->assertForbidden();
    }

    public function test_doctor_assignment_can_be_added_and_ended(): void
    {
        $admin = $this->makeUser('admin');
        $patient = Patient::create(['first_name' => 'Jane', 'last_name' => 'Roe']);

        $doctorStaff = Staff::create(['staff_id' => 'STF0010', 'first_name' => 'Doc', 'last_name' => 'Tor']);
        Department::create(['department_id' => 'DEP0001', 'department_name' => 'General']);
        $doctor = Doctor::create(['staff_id' => 'STF0010', 'department_id' => 'DEP0001']);

        $this->actingAs($admin)->post("/patients/{$patient->patient_id}/doctor-assignments", [
            'doctor_id' => $doctor->doctor_id,
            'role' => 'main_doctor',
        ])->assertRedirect('/patients');

        $assignment = $patient->doctorAssignments()->first();
        $this->assertNotNull($assignment);
        $this->assertSame('active', $assignment->status);

        $this->actingAs($admin)->post("/doctor-assignments/{$assignment->assignment_id}/end")
            ->assertRedirect('/patients');
        $this->assertSame('completed', $assignment->fresh()->status);
    }
}
