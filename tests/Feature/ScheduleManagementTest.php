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

class ScheduleManagementTest extends TestCase
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

    private function makeDoctorFixture(): void
    {
        Staff::create(['staff_id' => 'STF0010', 'first_name' => 'Doc', 'last_name' => 'Tor']);
        User::create(['staff_id' => 'STF0010', 'email' => 'doc@test.local', 'password_hash' => Hash::make('secret'), 'role' => 'doctor']);
        Department::create(['department_id' => 'DEP0001', 'department_name' => 'Cardiology']);
        Doctor::create(['doctor_id' => 'DOC0001', 'staff_id' => 'STF0010', 'department_id' => 'DEP0001']);
    }

    private function listResponse(array $items = [], ?array $stats = null): array
    {
        return [
            'data' => $items,
            'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($items), 'per_page' => 20],
            'stats' => $stats ?? ['scheduled' => count($items), 'completed' => 0, 'on_leave' => 0, 'cancelled' => 0],
        ];
    }

    public function test_schedule_index_renders_list_and_stats(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/schedule*' => Http::response($this->listResponse([
                ['shift_id' => 'SH0001', 'staff_id' => 'STF0001', 'staff_name' => 'Test Admin', 'shift_date' => '2026-07-26', 'start_time' => '08:00:00', 'end_time' => '16:00:00', 'shift_type' => 'morning', 'status' => 'scheduled'],
            ], ['scheduled' => 1, 'completed' => 0, 'on_leave' => 0, 'cancelled' => 0])),
        ]);

        $this->actingAs($admin)->get('/schedule')->assertOk()->assertSee('Test Admin')->assertSee('SH0001');
    }

    public function test_admin_can_assign_view_edit_and_cancel_a_shift(): void
    {
        $admin = $this->makeAdmin();
        $this->makeDoctorFixture();

        Http::fake([
            '*/api/schedule/SH0001/cancel' => Http::response([
                'shift_id' => 'SH0001', 'staff_id' => 'STF0010', 'staff_name' => 'Doc Tor',
                'shift_date' => '2026-08-01', 'start_time' => '08:00:00', 'end_time' => '16:00:00',
                'shift_type' => 'morning', 'status' => 'cancelled',
            ]),
            '*/api/schedule/SH0001' => Http::response([
                'shift_id' => 'SH0001', 'staff_id' => 'STF0010', 'staff_name' => 'Doc Tor',
                'shift_date' => '2026-08-01', 'start_time' => '08:00:00', 'end_time' => '16:00:00',
                'shift_type' => 'morning', 'status' => 'scheduled',
            ]),
            '*/api/schedule' => Http::response([
                'shift_id' => 'SH0001', 'staff_id' => 'STF0010', 'staff_name' => 'Doc Tor',
                'shift_date' => '2026-08-01', 'start_time' => '08:00:00', 'end_time' => '16:00:00',
                'shift_type' => 'morning', 'status' => 'scheduled',
            ], 201),
        ]);

        $create = $this->actingAs($admin)->post('/schedule', [
            'staff_id' => 'STF0010',
            'shift_date' => '2026-08-01',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'shift_type' => 'morning',
        ]);
        $create->assertRedirect('/schedule');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/schedule'
            && $request->method() === 'POST'
            && $request['staff_id'] === 'STF0010'
            && $request['shift_type'] === 'morning');

        $this->actingAs($admin)->get('/schedule/SH0001')->assertOk()->assertSee('Doc Tor');

        $edit = $this->actingAs($admin)->put('/schedule/SH0001', [
            'shift_date' => '2026-08-01',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'shift_type' => 'afternoon',
            'status' => 'scheduled',
        ]);
        $edit->assertRedirect('/schedule');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/schedule/SH0001'
            && $request->method() === 'PUT'
            && $request['shift_type'] === 'afternoon');

        $cancel = $this->actingAs($admin)->post('/schedule/SH0001/cancel');
        $cancel->assertRedirect('/schedule');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/schedule/SH0001/cancel'
            && $request->method() === 'POST');
    }

    public function test_staff_search_returns_matching_active_staff(): void
    {
        $admin = $this->makeAdmin();
        $this->makeDoctorFixture();

        $response = $this->actingAs($admin)->get('/staff/search?q=Doc');
        $response->assertOk();
        $response->assertJsonFragment(['id' => 'STF0010', 'role' => 'doctor', 'department' => 'Cardiology']);
    }

    public function test_receptionist_can_view_but_not_manage_schedule(): void
    {
        Staff::create(['staff_id' => 'STF0020', 'first_name' => 'Rita', 'last_name' => 'Front']);
        $receptionist = User::create([
            'staff_id' => 'STF0020',
            'email' => 'reception@test.local',
            'password_hash' => Hash::make('secret'),
            'role' => 'receptionist',
        ]);

        Http::fake([
            '*/api/schedule*' => Http::response($this->listResponse([])),
        ]);

        $this->actingAs($receptionist)->get('/schedule')->assertOk();
        $this->actingAs($receptionist)->get('/schedule/create')->assertForbidden();
        $this->actingAs($receptionist)->post('/schedule', ['staff_id' => 'STF0020'])->assertForbidden();
    }
}
