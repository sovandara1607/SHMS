<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageReadViewsTest extends TestCase
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

    private function listResponse(array $items = []): array
    {
        return ['data' => $items, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items), 'per_page' => 20]];
    }

    public function test_prescriptions_page_renders_via_central_service(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/pharmacy*' => Http::response([
                'medicines' => $this->listResponse([]),
                'batches' => $this->listResponse([]),
                'prescriptions' => $this->listResponse([
                    ['prescription_id' => 'PRS0001', 'patient_name' => 'John Doe', 'prescription_date' => '2026-07-26', 'notes' => 'Take with food'],
                ]),
                'dispensing_records' => $this->listResponse([]),
                'stats' => ['total' => 0, 'available' => 0, 'low_stock' => 0, 'expired_batches' => 0],
            ]),
        ]);

        $this->actingAs($admin)->get('/prescriptions')->assertOk()->assertSee('PRS0001')->assertSee('John Doe');
    }

    public function test_lab_reports_page_renders_via_central_service(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/lab*' => Http::response([
                'orders' => $this->listResponse([]),
                'results' => $this->listResponse([]),
                'reports' => $this->listResponse([
                    ['lab_report_id' => 'LR0001', 'patient_name' => 'John Doe', 'test_order_id' => 'LAB0001', 'generated_at' => '2026-07-26 10:00:00'],
                ]),
                'stats' => ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'pending_results' => 0],
            ]),
        ]);

        $this->actingAs($admin)->get('/lab-reports')->assertOk()->assertSee('LR0001')->assertSee('John Doe');
    }
}
