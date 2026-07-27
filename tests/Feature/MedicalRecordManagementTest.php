<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MedicalRecordManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        Staff::create(['staff_id' => 'STF0001', 'first_name' => 'Test', 'last_name' => 'Admin']);

        return User::create([
            'staff_id' => 'STF0001',
            'email' => 'admin@test.local',
            'password_hash' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    /** Same rationale as AppointmentManagementTest — local exists: checks need local fixture rows. */
    private function makeLocalFixtures(): void
    {
        Patient::create(['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe']);
        Staff::create(['staff_id' => 'STF0010', 'first_name' => 'Doc', 'last_name' => 'Tor']);
        Department::create(['department_id' => 'DEP0001', 'department_name' => 'General']);
        Doctor::create(['doctor_id' => 'DOC0001', 'staff_id' => 'STF0010', 'department_id' => 'DEP0001']);
        Medicine::create(['medicine_id' => 'MED0001', 'medicine_name' => 'Paracetamol 500mg']);
    }

    private function recordPayload(array $overrides = []): array
    {
        return array_merge([
            'medical_record_id' => 'MR0001',
            'patient_id' => 'PAT0001',
            'doctor_id' => 'DOC0001',
            'appointment_id' => null,
            'symptoms' => 'Cough',
            'diagnosis' => 'Common cold',
            'treatment_notes' => 'Rest',
            'created_by' => 'STF0001',
            'created_at' => now()->toIso8601String(),
            'patient' => ['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe', 'date_of_birth' => '1988-04-12'],
            'doctor_name' => 'David Heart',
            'adjustments' => [],
            'prescriptions' => [],
        ], $overrides);
    }

    private function listResponse(array $items = []): array
    {
        return ['data' => $items, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items), 'per_page' => 20]];
    }

    public function test_admin_can_create_view_and_adjust_a_medical_record(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalFixtures();

        Http::fake([
            '*/api/medical-records' => Http::response($this->recordPayload(), 201),
            '*/api/medical-records/MR0001/adjust' => Http::response($this->recordPayload([
                'adjustments' => [[
                    'adjustment_id' => 'ADJ001', 'medical_record_id' => 'MR0001', 'symptoms' => null,
                    'diagnosis' => 'Resolving', 'treatment_notes' => null, 'adjusted_by' => 'STF0001',
                    'adjusted_at' => now()->toIso8601String(), 'reason' => 'Follow-up',
                ]],
            ])),
            '*/api/medical-records/MR0001' => Http::response($this->recordPayload()),
            '*/api/medicines/all' => Http::response([]),
        ]);

        $create = $this->actingAs($admin)->post('/medical-records', [
            'patient_id' => 'PAT0001',
            'doctor_id' => 'DOC0001',
            'diagnosis' => 'Common cold',
        ]);
        $create->assertRedirect('/medical-records');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/medical-records'
            && $request['created_by'] === 'STF0001'
            && $request['diagnosis'] === 'Common cold');

        $this->actingAs($admin)->get('/medical-records/MR0001')->assertOk()->assertSee('Common cold');

        $adjust = $this->actingAs($admin)->post('/medical-records/MR0001/adjust', [
            'diagnosis' => 'Resolving',
            'reason' => 'Follow-up',
        ]);
        $adjust->assertRedirect('/medical-records');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/medical-records/MR0001/adjust'
            && $request['adjusted_by'] === 'STF0001'
            && $request['reason'] === 'Follow-up');
    }

    public function test_prescription_can_be_created(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalFixtures();
        Medicine::create(['medicine_id' => 'MED0003', 'medicine_name' => 'Warfarin 5mg']);

        Http::fake([
            '*/api/medical-records/MR0001/prescriptions' => Http::response([
                'prescription_id' => 'PRS0001',
                'medical_record_id' => 'MR0001',
            ], 201),
        ]);

        $response = $this->actingAs($admin)->post('/medical-records/MR0001/prescriptions', [
            'items' => [
                ['medicine_id' => 'MED0001'],
                ['medicine_id' => 'MED0003'],
            ],
        ]);

        $response->assertRedirect('/medical-records');
        $response->assertSessionHas('success', 'Prescription PRS0001 created.');
    }

    public function test_report_can_be_generated(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalFixtures();

        Http::fake([
            '*/api/medical-records/MR0001/reports' => Http::response([
                'report_id' => 'REP0001', 'medical_record_id' => 'MR0001', 'patient_id' => 'PAT0001',
                'report_type' => 'Progress Report', 'report_content' => 'Patient recovering well.',
                'generated_by' => 'STF0001', 'generated_at' => now()->toIso8601String(),
            ], 201),
        ]);

        $response = $this->actingAs($admin)->post('/medical-records/MR0001/reports', [
            'report_type' => 'Progress Report',
            'report_content' => 'Patient recovering well.',
        ]);

        $response->assertRedirect('/medical-records');
        $response->assertSessionHas('success', 'Report REP0001 generated.');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/medical-records/MR0001/reports'
            && $request['generated_by'] === 'STF0001'
            && $request['report_type'] === 'Progress Report');
    }

    public function test_medical_reports_list_renders(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/medical-reports*' => Http::response($this->listResponse([
                ['report_id' => 'REP0001', 'patient_name' => 'John Doe', 'report_type' => 'Progress Report', 'generated_at' => now()->toIso8601String()],
            ])),
        ]);

        $this->actingAs($admin)->get('/medical-reports')->assertOk()->assertSee('John Doe');
    }

    public function test_vital_signs_can_be_listed_and_recorded(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalFixtures();

        Http::fake([
            '*/api/vital-signs*' => Http::response($this->listResponse([
                ['vital_sign_id' => 'VS0001', 'patient_id' => 'PAT0001', 'patient_name' => 'John Doe', 'temperature' => 37.0, 'blood_pressure' => '120/80', 'heart_rate' => 70, 'height' => null, 'weight' => null, 'recorded_at' => now()->toIso8601String()],
            ])),
        ]);

        $this->actingAs($admin)->get('/vital-signs')->assertOk()->assertSee('John Doe');

        Http::fake([
            '*/api/vital-signs' => Http::response(['vital_sign_id' => 'VS0002', 'patient_id' => 'PAT0001'], 201),
        ]);

        $this->actingAs($admin)->post('/vital-signs', [
            'patient_id' => 'PAT0001',
            'temperature' => 36.9,
        ])->assertRedirect('/vital-signs');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/vital-signs'
            && $request->method() === 'POST'
            && $request['recorded_by'] === 'STF0001');
    }
}
