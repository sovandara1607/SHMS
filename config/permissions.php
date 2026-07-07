<?php

/**
 * Role-Based Access Control matrix.
 *
 * Each permission is a coarse capability string ("module.action").
 * Routes declare a required permission; RoleMiddleware checks the
 * logged-in user's role against this map. '*' grants everything (admin).
 */

return [
    // ---- canonical role list (matches users.role enum) ----
    'roles' => [
        'super_admin'    => 'Super Admin',
        'admin'          => 'Admin',
        'doctor'         => 'Doctor',
        'nurse'          => 'Nurse',
        'receptionist'   => 'Receptionist',
        'pharmacist'     => 'Pharmacist',
        'lab_technician' => 'Lab Technician',
    ],

    // ---- permission grants per role ----
    'permissions' => [
        // super_admin is protected: full access, cannot be edited via the
        // Roles & Permissions UI.
        'super_admin' => ['*'],
        'admin' => ['*'],

        'receptionist' => [
            'dashboard.view',
            'patient.view', 'patient.create', 'patient.update', 'patient.discharge',
            'appointment.view', 'appointment.create', 'appointment.update', 'appointment.cancel',
            'room.view', 'room.assign',
            'schedule.view',
            'bill.view', 'bill.create', 'bill.update',
            'payment.view', 'payment.create',
            'profile.view', 'profile.update',
        ],

        'doctor' => [
            'dashboard.view',
            'patient.view',
            'appointment.view',
            'medical_record.view', 'medical_record.create', 'medical_record.adjust',
            'treatment.view', 'treatment.create',
            'prescription.view', 'prescription.create',
            'procedure.view', 'procedure.create',
            'lab_order.view', 'lab_order.create',
            'lab_result.view',
            'medical_report.view', 'medical_report.create',
            'vital_signs.view',
            'profile.view', 'profile.update',
        ],

        'nurse' => [
            'dashboard.view',
            'patient.view',
            'vital_signs.view', 'vital_signs.create',
            'medical_record.view',
            'room.view',
            'profile.view', 'profile.update',
        ],

        'pharmacist' => [
            'dashboard.view',
            'medicine.view', 'medicine.create', 'medicine.update',
            'medicine_batch.view', 'medicine_batch.create', 'medicine_batch.update',
            'drug_interaction.view', 'drug_interaction.manage',
            'drug_substitution.view', 'drug_substitution.manage',
            'dispensing.view', 'dispensing.create',
            'prescription.view',
            'profile.view', 'profile.update',
        ],

        'lab_technician' => [
            'dashboard.view',
            'lab_order.view', 'lab_order.update',
            'lab_result.view', 'lab_result.create',
            'lab_equipment.view', 'lab_equipment.manage',
            'lab_report.view', 'lab_report.create',
            'patient.view',
            'profile.view', 'profile.update',
        ],
    ],
];
