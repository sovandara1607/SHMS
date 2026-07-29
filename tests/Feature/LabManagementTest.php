<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\LabTechnician;
use App\Models\LabTestOrder;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LabManagementTest extends TestCase
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
        Patient::create(['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe']);
        Staff::create(['staff_id' => 'STF0010', 'first_name' => 'Doc', 'last_name' => 'Tor']);
        Department::create(['department_id' => 'DEP0001', 'department_name' => 'General']);
        Doctor::create(['doctor_id' => 'DOC0001', 'staff_id' => 'STF0010', 'department_id' => 'DEP0001']);
        Staff::create(['staff_id' => 'STF0020', 'first_name' => 'Lara', 'last_name' => 'Tech']);
        LabTechnician::create(['technician_id' => 'TEC0001', 'staff_id' => 'STF0020']);
        LabTestOrder::create(['test_order_id' => 'LAB0001', 'patient_id' => 'PAT0001', 'doctor_id' => 'DOC0001', 'test_name' => 'Blood Test']);
    }

    private function listResponse(array $items = [], string $pageKey = 'current_page'): array
    {
        return ['data' => $items, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items), 'per_page' => 20]];
    }

    public function test_lab_orders_index_renders_lists_and_stats(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalFixtures();

        Http::fake([
            '*/api/lab*' => Http::response([
                'orders' => $this->listResponse([[
                    'test_order_id' => 'LAB0001', 'patient_id' => 'PAT0001', 'doctor_id' => 'DOC0001',
                    'test_name' => 'Blood Test', 'status' => 'pending', 'patient_name' => 'John Doe',
                    'doctor_name' => 'David Heart', 'technician_name' => null, 'priority' => 'routine',
                ]]),
                'results' => $this->listResponse([]),
                'reports' => $this->listResponse([]),
                'stats' => ['pending' => 1, 'in_progress' => 0, 'completed' => 0, 'pending_results' => 0],
            ]),
        ]);

        $this->actingAs($admin)->get('/lab-orders')->assertOk()->assertSee('LAB0001')->assertSee('John Doe');
    }

    public function test_admin_can_create_a_lab_order_and_update_its_status(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalFixtures();

        Http::fake([
            '*/api/lab-orders' => Http::response([
                'test_order_id' => 'LAB0001', 'patient_id' => 'PAT0001', 'doctor_id' => 'DOC0001',
                'technician_id' => null, 'test_name' => 'Blood Test', 'status' => 'pending',
            ], 201),
            '*/api/lab-orders/LAB0001/status' => Http::response([
                'test_order_id' => 'LAB0001', 'status' => 'in_progress',
            ]),
        ]);

        $create = $this->actingAs($admin)->post('/lab-orders', [
            'patient_id' => 'PAT0001',
            'doctor_id' => 'DOC0001',
            'test_name' => 'Blood Test',
        ]);
        $create->assertRedirect('/lab-orders');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/lab-orders'
            && $request->method() === 'POST'
            && $request['test_name'] === 'Blood Test');

        $this->actingAs($admin)->post('/lab-orders/LAB0001/status', [
            'status' => 'in_progress',
        ])->assertRedirect('/lab-orders');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/lab-orders/LAB0001/status'
            && $request['resolved_technician_id'] === null);
    }

    public function test_technician_can_enter_a_result_and_duplicate_is_blocked(): void
    {
        $this->makeLocalFixtures();
        $technicianUser = User::create([
            'staff_id' => 'STF0020',
            'email' => 'tech@test.local',
            'password_hash' => Hash::make('secret'),
            'role' => 'lab_technician',
        ]);

        Http::fake([
            '*/api/lab-results' => Http::sequence()
                ->push(['test_result_id' => 'LRS0001', 'test_order_id' => 'LAB0001', 'lab_report_id' => 'LR0001'], 201)
                ->push(['message' => 'A result has already been entered for this order.'], 409),
        ]);

        $response = $this->actingAs($technicianUser)->post('/lab-results', [
            'test_order_id' => 'LAB0001',
            'result_value' => 'Normal',
            'result_status' => 'normal',
        ]);
        $response->assertRedirect('/lab-orders');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/lab-results'
            && $request['entered_by'] === 'TEC0001'
            && $request['generated_by'] === 'STF0020');

        $dup = $this->actingAs($technicianUser)->post('/lab-results', [
            'test_order_id' => 'LAB0001',
            'result_value' => 'dup',
            'result_status' => 'normal',
        ]);
        $dup->assertRedirect('/lab-orders');
        $dup->assertSessionHas('error', 'A result has already been entered for this order.');
    }

    public function test_lab_equipment_page_renders(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/lab-equipment' => Http::response([
                ['equipment_id' => 'EQ0001', 'equipment_name' => 'Centrifuge', 'equipment_type' => 'Analyzer', 'laboratory_name' => 'Main Lab', 'availability_status' => 'available', 'last_maintenance_date' => '2026-06-01'],
            ]),
        ]);

        $this->actingAs($admin)->get('/lab-equipment')->assertOk()->assertSee('Centrifuge');
    }
}
