<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingManagementTest extends TestCase
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
    private function makeLocalPatient(): void
    {
        Patient::create(['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe']);
    }

    private function billPayload(array $overrides = []): array
    {
        return array_merge([
            'bill_id' => 'BIL0001',
            'patient_id' => 'PAT0001',
            'appointment_id' => null,
            'generated_by' => 'STF0001',
            'bill_date' => now()->toDateString(),
            'total_amount' => 0,
            'status' => 'unpaid',
            'patient' => ['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe', 'date_of_birth' => '1988-04-12'],
            'items' => [],
            'payments' => [],
            'paid_amount' => 0,
            'balance' => 0,
        ], $overrides);
    }

    private function listResponse(array $items = []): array
    {
        return ['data' => $items, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items), 'per_page' => 20]];
    }

    public function test_billing_index_renders_bills_and_payments_with_stats(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/bills*' => Http::response([
                'bills' => $this->listResponse([[
                    'bill_id' => 'BIL0001', 'patient_id' => 'PAT0001', 'bill_date' => '2026-07-26',
                    'total_amount' => 100, 'status' => 'unpaid', 'patient_name' => 'John Doe', 'paid_amount' => 0, 'item_count' => 1,
                ]]),
                'payments' => $this->listResponse([]),
                'stats' => ['total_amount' => 100, 'total_revenue' => 0, 'pending_amount' => 100, 'unpaid' => 1, 'partially_paid' => 0, 'paid' => 0],
            ]),
        ]);

        $this->actingAs($admin)->get('/bills')->assertOk()->assertSee('BIL0001')->assertSee('John Doe');
    }

    public function test_admin_can_create_a_bill_add_an_item_and_view_it(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalPatient();

        Http::fake([
            '*/api/bills' => Http::response($this->billPayload(), 201),
            '*/api/bills/BIL0001/items' => Http::response($this->billPayload([
                'total_amount' => 100,
                'items' => [['bill_item_id' => 'BI0001', 'bill_id' => 'BIL0001', 'item_type' => 'service', 'description' => 'Consultation', 'quantity' => 2, 'unit_price' => 50, 'subtotal' => 100]],
            ]), 201),
            '*/api/bills/BIL0001' => Http::response($this->billPayload([
                'total_amount' => 100,
                'items' => [['bill_item_id' => 'BI0001', 'bill_id' => 'BIL0001', 'item_type' => 'service', 'description' => 'Consultation', 'quantity' => 2, 'unit_price' => 50, 'subtotal' => 100]],
                'balance' => 100,
            ])),
        ]);

        $create = $this->actingAs($admin)->post('/bills', ['patient_id' => 'PAT0001']);
        $create->assertRedirect('/bills');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/bills'
            && $request->method() === 'POST'
            && $request['generated_by'] === 'STF0001');

        $this->actingAs($admin)->post('/bills/BIL0001/items', [
            'item_type' => 'service',
            'description' => 'Consultation',
            'quantity' => 2,
            'unit_price' => 50,
        ])->assertRedirect('/bills');

        $this->actingAs($admin)->get('/bills/BIL0001')->assertOk()->assertSee('Consultation');
    }

    public function test_payment_over_balance_shows_validation_error_and_already_paid_shows_conflict(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalPatient();

        Http::fake([
            '*/api/bills/BIL0001/pay' => Http::sequence()
                ->push(['message' => 'The amount paid field must not be greater than 40.', 'errors' => ['amount_paid' => ['The amount paid field must not be greater than 40.']]], 422)
                ->push(['message' => 'This bill is already fully paid.'], 409),
        ]);

        $over = $this->actingAs($admin)->post('/bills/BIL0001/pay', [
            'amount_paid' => 9999,
            'payment_method' => 'cash',
        ]);
        $over->assertRedirect();
        $over->assertSessionHasErrors('amount_paid');

        $again = $this->actingAs($admin)->post('/bills/BIL0001/pay', [
            'amount_paid' => 1,
            'payment_method' => 'cash',
        ]);
        $again->assertRedirect();
        $again->assertSessionHas('error', 'This bill is already fully paid.');
    }
}
