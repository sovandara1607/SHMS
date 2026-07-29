<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentManagementTest extends TestCase
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

    /**
     * The local `exists:patient,patient_id` / `exists:doctor,doctor_id`
     * validation rules run against Database-final's own connection, which
     * is the same physical Postgres database central-service writes to in
     * production. Tests use a fresh schema, so these fixture rows stand in
     * for that shared data.
     */
    private function makeLocalPatientAndDoctor(): void
    {
        Patient::create(['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe']);
        Staff::create(['staff_id' => 'STF0010', 'first_name' => 'Doc', 'last_name' => 'Tor']);
        Department::create(['department_id' => 'DEP0001', 'department_name' => 'General']);
        Doctor::create(['doctor_id' => 'DOC0001', 'staff_id' => 'STF0010', 'department_id' => 'DEP0001']);
    }

    private function appointmentPayload(array $overrides = []): array
    {
        return array_merge([
            'appointment_id' => 'APT0001',
            'patient_id' => 'PAT0001',
            'doctor_id' => 'DOC0001',
            'booked_by' => 'STF0001',
            'appointment_date' => '2026-08-01',
            'appointment_time' => '10:00:00',
            'reason' => 'Checkup',
            'status' => 'scheduled',
            'cancellation_reason' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'patient' => ['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe', 'date_of_birth' => '1988-04-12'],
            'doctor_name' => 'David Heart',
            'booked_by_name' => 'System Admin',
        ], $overrides);
    }

    private function listResponse(array $items = []): array
    {
        return ['data' => $items, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items), 'per_page' => 20]];
    }

    public function test_admin_can_book_view_edit_and_cancel_an_appointment(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalPatientAndDoctor();

        Http::fake([
            '*/api/appointments' => Http::response($this->appointmentPayload(), 201),
            '*/api/appointments/APT0001' => Http::response($this->appointmentPayload(['reason' => 'Follow-up'])),
            '*/api/appointments/APT0001/cancel' => Http::response($this->appointmentPayload(['status' => 'cancelled', 'cancellation_reason' => 'No longer needed'])),
        ]);

        $create = $this->actingAs($admin)->post('/appointments', [
            'patient_id' => 'PAT0001',
            'doctor_id' => 'DOC0001',
            'appointment_date' => '2026-08-01',
            'appointment_time' => '10:00',
            'reason' => 'Checkup',
        ]);
        $create->assertRedirect('/appointments');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/appointments'
            && $request->method() === 'POST'
            && $request['booked_by'] === 'STF0001');

        $this->actingAs($admin)->get('/appointments/APT0001')->assertOk();
        $this->actingAs($admin)->get('/appointments/APT0001/edit')->assertOk();

        $this->actingAs($admin)->put('/appointments/APT0001', [
            'patient_id' => 'PAT0001',
            'doctor_id' => 'DOC0001',
            'appointment_date' => '2026-08-01',
            'appointment_time' => '11:00',
            'reason' => 'Follow-up',
        ])->assertRedirect('/appointments');

        $this->actingAs($admin)->post('/appointments/APT0001/cancel', [
            'cancellation_reason' => 'No longer needed',
        ])->assertRedirect('/appointments');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/appointments/APT0001/cancel'
            && $request['cancellation_reason'] === 'No longer needed');
    }

    public function test_admin_can_mark_a_scheduled_appointment_completed(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalPatientAndDoctor();

        Http::fake([
            '*/api/appointments/APT0001/complete' => Http::response($this->appointmentPayload(['status' => 'completed'])),
        ]);

        $this->actingAs($admin)->post('/appointments/APT0001/complete')
            ->assertRedirect('/appointments')
            ->assertSessionHas('success', 'Appointment marked as completed.');

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/appointments/APT0001/complete'
            && $request->method() === 'POST');
    }

    public function test_completing_a_non_scheduled_appointment_shows_a_conflict_error(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalPatientAndDoctor();

        Http::fake([
            '*/api/appointments/APT0001/complete' => Http::response(['message' => 'Only a scheduled appointment can be marked completed.'], 409),
        ]);

        $this->actingAs($admin)->post('/appointments/APT0001/complete')
            ->assertRedirect()
            ->assertSessionHas('error', 'Only a scheduled appointment can be marked completed.');
    }

    public function test_double_booking_shows_a_conflict_error(): void
    {
        $admin = $this->makeAdmin();
        $this->makeLocalPatientAndDoctor();

        Http::fake([
            '*/api/appointments' => Http::response(['message' => 'That doctor already has a scheduled appointment in this slot.'], 409),
        ]);

        $response = $this->actingAs($admin)->post('/appointments', [
            'patient_id' => 'PAT0001',
            'doctor_id' => 'DOC0001',
            'appointment_date' => '2026-08-01',
            'appointment_time' => '10:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'That doctor already has a scheduled appointment in this slot.');
    }

    public function test_appointment_index_renders_list_and_stats(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/appointments*' => Http::response($this->listResponse([
                [
                    'appointment_id' => 'APT0001', 'patient_id' => 'PAT0001', 'doctor_id' => 'DOC0001',
                    'appointment_date' => '2026-08-01', 'appointment_time' => '10:00:00', 'reason' => 'Checkup',
                    'status' => 'scheduled', 'patient_name' => 'John Doe', 'doctor_name' => 'David Heart',
                ],
            ]) + ['stats' => ['today' => 1, 'this_week' => 2, 'scheduled' => 1, 'cancelled' => 0]]),
        ]);

        $this->actingAs($admin)->get('/appointments')->assertOk()->assertSee('APT0001')->assertSee('John Doe');
    }
}
