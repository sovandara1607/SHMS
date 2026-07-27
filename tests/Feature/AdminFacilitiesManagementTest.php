<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminFacilitiesManagementTest extends TestCase
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

    public function test_admin_can_create_and_update_a_department(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            '*/api/departments' => Http::response(['department_id' => 'DEP0001', 'department_name' => 'Cardiology', 'description' => null, 'capacity' => 10, 'status' => 'active'], 201),
            '*/api/departments/DEP0001' => Http::response(['department_id' => 'DEP0001', 'department_name' => 'Cardiology Updated', 'description' => null, 'capacity' => 20, 'status' => 'active']),
        ]);

        $create = $this->actingAs($admin)->post('/departments', ['department_name' => 'Cardiology', 'capacity' => 10]);
        $create->assertRedirect('/departments');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/departments'
            && $request->method() === 'POST'
            && $request['department_name'] === 'Cardiology');

        $this->actingAs($admin)->get('/departments/DEP0001/edit')->assertOk()->assertSee('Cardiology Updated');

        $update = $this->actingAs($admin)->put('/departments/DEP0001', ['department_name' => 'Cardiology Updated', 'capacity' => 20]);
        $update->assertRedirect('/departments');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/departments/DEP0001'
            && $request->method() === 'PUT'
            && $request['department_name'] === 'Cardiology Updated');
    }

    public function test_admin_can_create_a_room_add_a_bed_and_assign_a_patient(): void
    {
        $admin = $this->makeAdmin();
        Patient::create(['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe']);

        Http::fake([
            '*/api/rooms' => Http::response(['room_id' => 'RM0001', 'department_id' => null, 'room_number' => '101', 'room_type' => 'general', 'floor_number' => 1, 'status' => 'available'], 201),
            '*/api/rooms/RM0001/beds' => Http::response(['bed_id' => 'BED0001', 'room_id' => 'RM0001', 'bed_number' => '1', 'status' => 'available'], 201),
            '*/api/beds/BED0001/assign-data' => Http::response([
                'bed_id' => 'BED0001', 'room_id' => 'RM0001', 'bed_number' => '1', 'status' => 'available',
                'room' => ['room_id' => 'RM0001', 'room_number' => '101', 'room_type' => 'general'],
            ]),
            '*/api/beds/BED0001/assign' => Http::response(['assignment_id' => 'RA0001', 'room_id' => 'RM0001', 'bed_id' => 'BED0001', 'bed_number' => '1'], 201),
        ]);

        $roomCreate = $this->actingAs($admin)->post('/rooms', ['room_number' => '101', 'room_type' => 'general', 'floor_number' => 1]);
        $roomCreate->assertRedirect('/rooms');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/rooms'
            && $request->method() === 'POST'
            && $request['room_number'] === '101');

        $bedCreate = $this->actingAs($admin)->post('/rooms/RM0001/beds', ['bed_number' => '1']);
        $bedCreate->assertRedirect('/rooms');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/rooms/RM0001/beds'
            && $request['bed_number'] === '1');

        $this->actingAs($admin)->get('/beds/BED0001/assign')->assertOk();

        $assign = $this->actingAs($admin)->post('/beds/BED0001/assign', ['patient_id' => 'PAT0001']);
        $assign->assertRedirect('/rooms');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/beds/BED0001/assign'
            && $request['patient_id'] === 'PAT0001'
            && $request['assigned_by'] === 'STF0001');
    }

    public function test_bed_conflict_and_release_flow(): void
    {
        $admin = $this->makeAdmin();
        Patient::create(['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe']);

        Http::fake([
            '*/api/beds/BED0001/assign' => Http::response(['message' => 'This bed is not available.'], 409),
            '*/api/room-assignments/RA0001/release' => Http::response(['room_assignment_id' => 'RA0001', 'room_id' => 'RM0001', 'patient_id' => 'PAT0001']),
        ]);

        $assign = $this->actingAs($admin)->post('/beds/BED0001/assign', ['patient_id' => 'PAT0001']);
        $assign->assertRedirect();
        $assign->assertSessionHas('error', 'This bed is not available.');

        $release = $this->actingAs($admin)->post('/room-assignments/RA0001/release');
        $release->assertRedirect();
        $release->assertSessionHas('success', 'Bed released.');
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8100/api/room-assignments/RA0001/release'
            && $request->method() === 'POST');
    }
}
