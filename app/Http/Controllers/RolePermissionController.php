<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Admin/Super Admin screen for editing what each non-protected role can do.
 * super_admin and admin stay hardcoded wildcards in config/permissions.php
 * and can't be edited here (matches the Figma "Protected Role" treatment).
 */
class RolePermissionController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /** Capability catalog shown in the UI, grouped like the Figma matrix. */
    public static function catalog(): array
    {
        return [
            'Patient Management' => [
                'patient.view' => 'View Patients',
                'patient.create' => 'Add Patients',
                'patient.update' => 'Edit Patients',
                'patient.discharge' => 'Discharge Patients',
            ],
            'Appointments' => [
                'appointment.view' => 'View Appointments',
                'appointment.create' => 'Book Appointments',
                'appointment.update' => 'Manage Appointments',
                'appointment.cancel' => 'Cancel Appointments',
            ],
            'Medical Records & Treatment' => [
                'medical_record.view' => 'View Medical Records',
                'medical_record.create' => 'Create Medical Records',
                'medical_record.adjust' => 'Adjust Medical Records',
                'treatment.view' => 'View Treatments',
                'treatment.create' => 'Create Treatments',
                'prescription.view' => 'View Prescriptions',
                'prescription.create' => 'Create Prescriptions',
                'procedure.view' => 'View Procedures',
                'procedure.create' => 'Create Procedures',
                'medical_report.view' => 'View Medical Reports',
                'medical_report.create' => 'Generate Medical Reports',
                'vital_signs.view' => 'View Vital Signs',
                'vital_signs.create' => 'Record Vital Signs',
            ],
            'Pharmacy' => [
                'medicine.view' => 'View Pharmacy',
                'medicine.create' => 'Add Medicines',
                'medicine.update' => 'Update Medicines',
                'medicine_batch.view' => 'View Batches',
                'medicine_batch.create' => 'Add Batches',
                'medicine_batch.update' => 'Update Batches',
                'drug_interaction.view' => 'View Drug Interactions',
                'drug_interaction.manage' => 'Manage Drug Interactions',
                'drug_substitution.view' => 'View Drug Substitutions',
                'drug_substitution.manage' => 'Manage Drug Substitutions',
                'dispensing.view' => 'View Dispensing Records',
                'dispensing.create' => 'Dispense Medicine',
            ],
            'Laboratory' => [
                'lab_order.view' => 'View Lab Orders',
                'lab_order.create' => 'Create Lab Orders',
                'lab_order.update' => 'Update Lab Orders',
                'lab_result.view' => 'View Lab Results',
                'lab_result.create' => 'Enter Lab Results',
                'lab_equipment.view' => 'View Lab Equipment',
                'lab_equipment.manage' => 'Manage Lab Equipment',
                'lab_report.view' => 'View Lab Reports',
                'lab_report.create' => 'Generate Lab Reports',
            ],
            'Billing' => [
                'bill.view' => 'View Billing',
                'bill.create' => 'Create Bills',
                'bill.update' => 'Manage Billing',
                'payment.view' => 'View Payments',
                'payment.create' => 'Process Payment',
            ],
            'Rooms & Schedule' => [
                'room.view' => 'View Rooms & Beds',
                'room.assign' => 'Assign Rooms & Beds',
                'schedule.view' => 'View Schedules',
            ],
            'Staff & System' => [
                'staff.manage' => 'Manage Staff & Departments',
                'report.view' => 'View Reports',
            ],
        ];
    }

    public function index()
    {
        $roles = config('permissions.roles');
        $selected = array_key_first(array_diff_key($roles, array_flip(['super_admin', 'admin'])));

        return view('roles-permissions.index', [
            'roles' => $roles,
            'selected' => $selected,
            'catalog' => self::catalog(),
            'granted' => RolePermission::where('role', $selected)->pluck('capability')->all(),
        ]);
    }

    public function panel(string $role)
    {
        return view('roles-permissions.panel', [
            'roles' => config('permissions.roles'),
            'selected' => $role,
            'catalog' => self::catalog(),
            'granted' => in_array($role, ['super_admin', 'admin'], true)
                ? ['*']
                : RolePermission::where('role', $role)->pluck('capability')->all(),
        ]);
    }

    public function update(Request $request, string $role)
    {
        if (in_array($role, ['super_admin', 'admin'], true)) {
            abort(403, 'This role is protected and cannot be edited.');
        }

        $allCapabilities = collect(self::catalog())->flatMap(fn ($caps) => array_keys($caps))->all();
        $selected = array_intersect($request->input('capabilities', []), $allCapabilities);

        RolePermission::where('role', $role)->delete();
        foreach ($selected as $capability) {
            RolePermission::create(['role' => $role, 'capability' => $capability]);
        }

        $this->audit->log('role_permission.update', 'role', $role, ['capabilities' => array_values($selected)]);

        return redirect('/roles-permissions')->with('success', ucfirst(str_replace('_', ' ', $role)) . ' permissions updated.')->with('reopen_role', $role);
    }
}
