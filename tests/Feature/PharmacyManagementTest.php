<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PharmacyManagementTest extends TestCase
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

    /** Same rationale as prior waves — local exists: checks need local fixture rows. */
    private function makeLocalFixtures(): void
    {
        Medicine::create(['medicine_id' => 'MED0001', 'medicine_name' => 'Paracetamol 500mg']);
        Patient::create(['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe']);
    }

    private function listResponse(array $items = []): array
    {
        return ['data' => $items, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items), 'per_page' => 20]];
    }

    public function test_medicines_index_renders_all_tabs(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/pharmacy*' => Http::response([
                'medicines' => $this->listResponse([['medicine_id' => 'MED0001', 'medicine_name' => 'Paracetamol 500mg', 'medicine_type' => 'Tablet', 'manufacturer' => 'Acme', 'unit_price' => 5, 'stock_quantity' => 100, 'status' => 'available']]),
                'batches' => $this->listResponse([]),
                'prescriptions' => $this->listResponse([]),
                'dispensing_records' => $this->listResponse([]),
                'stats' => ['total' => 1, 'available' => 1, 'low_stock' => 0, 'expired_batches' => 0],
            ]),
        ]);

        $this->actingAs($admin)->get('/medicines')->assertOk()->assertSee('Paracetamol 500mg');
    }

    public function test_admin_can_add_a_medicine_and_a_batch(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalFixtures();

        Http::fake([
            '*/api/medicines' => Http::response(['medicine_id' => 'MED0002', 'medicine_name' => 'Ibuprofen 200mg'], 201),
            '*/api/medicine-batches' => Http::response(['batch_id' => 'BAT0001', 'medicine_id' => 'MED0001', 'batch_number' => 'B-1', 'manufacture_date' => null, 'expiry_date' => null, 'quantity' => 50, 'status' => 'valid'], 201),
            '*/api/medicine-batches/BAT0001' => Http::response([
                'batch' => ['batch_id' => 'BAT0001', 'medicine_id' => 'MED0001', 'batch_number' => 'B-1', 'manufacture_date' => null, 'expiry_date' => null, 'quantity' => 50, 'status' => 'valid'],
                'medicine' => ['medicine_id' => 'MED0001', 'medicine_name' => 'Paracetamol 500mg'],
            ]),
        ]);

        $medCreate = $this->actingAs($admin)->post('/medicines', ['medicine_name' => 'Ibuprofen 200mg']);
        $medCreate->assertRedirect('/medicines');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/medicines'
            && $request->method() === 'POST'
            && $request['medicine_name'] === 'Ibuprofen 200mg');

        $batchCreate = $this->actingAs($admin)->post('/medicine-batches', [
            'medicine_id' => 'MED0001',
            'batch_number' => 'B-1',
            'quantity' => 50,
        ]);
        $batchCreate->assertRedirect('/medicines');

        $this->actingAs($admin)->get('/medicine-batches/BAT0001')->assertOk()->assertSee('Paracetamol 500mg');
    }

    public function test_dispense_succeeds_and_duplicate_is_rejected(): void
    {
        $this->makeLocalFixtures();
        Staff::create(['staff_id' => 'STF0010', 'first_name' => 'Doc', 'last_name' => 'Tor']);
        \App\Models\Department::create(['department_id' => 'DEP0001', 'department_name' => 'General']);
        \App\Models\Doctor::create(['doctor_id' => 'DOC0001', 'staff_id' => 'STF0010', 'department_id' => 'DEP0001']);
        \App\Models\MedicalRecord::create(['medical_record_id' => 'MR0001', 'patient_id' => 'PAT0001', 'doctor_id' => 'DOC0001', 'diagnosis' => 'Test']);
        Staff::create(['staff_id' => 'STF0020', 'first_name' => 'Perry', 'last_name' => 'Pharm']);
        Pharmacist::create(['pharmacist_id' => 'PHA0001', 'staff_id' => 'STF0020']);
        \App\Models\Prescription::create(['prescription_id' => 'PRS0001', 'medical_record_id' => 'MR0001', 'patient_id' => 'PAT0001', 'doctor_id' => 'DOC0001', 'prescription_date' => now()->toDateString()]);
        $pharmacistUser = User::create([
            'staff_id' => 'STF0020',
            'email' => 'pharm@test.local',
            'password_hash' => Hash::make('secret'),
            'role' => 'pharmacist',
        ]);

        Http::fake([
            '*/api/dispensing' => Http::sequence()
                ->push(['dispensing_id' => 'DSP0001'], 201)
                ->push(['message' => 'This prescription has already been dispensed.'], 422),
        ]);

        $first = $this->actingAs($pharmacistUser)->post('/dispensing', [
            'prescription_id' => 'PRS0001',
            'patient_id' => 'PAT0001',
        ]);
        $first->assertRedirect('/medicines');
        $first->assertSessionHas('success');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/dispensing'
            && $request['pharmacist_id'] === 'PHA0001');

        $dup = $this->actingAs($pharmacistUser)->post('/dispensing', [
            'prescription_id' => 'PRS0001',
            'patient_id' => 'PAT0001',
        ]);
        $dup->assertRedirect('/medicines');
        $dup->assertSessionHas('error', 'This prescription has already been dispensed.');
    }
}
