<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Bed;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\DrugInteraction;
use App\Models\DrugSubstitution;
use App\Models\Laboratory;
use App\Models\LabTechnician;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\Pharmacist;
use App\Models\Receptionist;
use App\Models\Room;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds demo data: departments, one staff + login per role, role-specific
 * profiles, sample patients, medicines, an appointment and a bill.
 * All demo logins use the password:  Password123!
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hash = Hash::make('Password123!');

        foreach ([
            ['DEP0001', 'General Medicine', 'General outpatient and inpatient care', 50],
            ['DEP0002', 'Cardiology', 'Heart and vascular care', 30],
            ['DEP0003', 'Laboratory Services', 'Diagnostic laboratory', 20],
            ['DEP0004', 'Pharmacy', 'Medicine dispensing unit', 15],
        ] as [$id, $name, $desc, $cap]) {
            Department::create(['department_id' => $id, 'department_name' => $name, 'description' => $desc, 'capacity' => $cap]);
        }

        Laboratory::create(['laboratory_id' => 'LABO001', 'laboratory_name' => 'Central Lab', 'location' => 'Ground Floor, Block B']);

        // room_id, department_id, room_number, room_type, floor, bed count
        foreach ([
            ['RM0001', 'DEP0001', '101', 'general', 1, 4],
            ['RM0002', 'DEP0001', '102', 'general', 1, 4],
            ['RM0003', 'DEP0001', '103', 'private', 1, 1],
            ['RM0004', 'DEP0002', '201', 'private', 2, 1],
            ['RM0005', 'DEP0002', '202', 'icu', 2, 2],
            ['RM0006', null, '301', 'emergency', 3, 3],
        ] as [$roomId, $deptId, $number, $type, $floor, $bedCount]) {
            Room::create(['room_id' => $roomId, 'department_id' => $deptId, 'room_number' => $number, 'room_type' => $type, 'floor_number' => $floor]);
            for ($b = 1; $b <= $bedCount; $b++) {
                Bed::create(['room_id' => $roomId, 'bed_number' => (string) $b]);
            }
        }

        // staff_id, first, last, role, email
        $people = [
            ['STF0001', 'System', 'Admin', 'admin', 'admin@hospital.test'],
            ['STF0002', 'David', 'Heart', 'doctor', 'doctor@hospital.test'],
            ['STF0003', 'Nora', 'Care', 'nurse', 'nurse@hospital.test'],
            ['STF0004', 'Rita', 'Front', 'receptionist', 'reception@hospital.test'],
            ['STF0005', 'Paul', 'Pharm', 'pharmacist', 'pharmacist@hospital.test'],
            ['STF0006', 'Lara', 'Tech', 'lab_technician', 'labtech@hospital.test'],
            ['STF0007', 'Super', 'Admin', 'super_admin', 'superadmin@hospital.test'],
        ];
        $i = 1;
        foreach ($people as [$sid, $first, $last, $role, $email]) {
            Staff::create([
                'staff_id' => $sid, 'first_name' => $first, 'last_name' => $last,
                'gender' => 'other', 'phone_number' => '012000000' . $i, 'hire_date' => now()->toDateString(),
            ]);
            User::create([
                'user_id' => 'USR000' . $i, 'staff_id' => $sid, 'email' => $email,
                'password_hash' => $hash, 'role' => $role,
            ]);
            $i++;
        }

        Doctor::create(['doctor_id' => 'DOC0001', 'staff_id' => 'STF0002', 'department_id' => 'DEP0002', 'specialization' => 'Cardiology', 'license_number' => 'LIC-DOC-001']);
        Nurse::create(['nurse_id' => 'NUR0001', 'staff_id' => 'STF0003', 'department_id' => 'DEP0001', 'ward_name' => 'Ward A']);
        Receptionist::create(['receptionist_id' => 'REC0001', 'staff_id' => 'STF0004', 'counter_number' => 'Counter 1']);
        Pharmacist::create(['pharmacist_id' => 'PHA0001', 'staff_id' => 'STF0005', 'license_number' => 'LIC-PHA-001', 'pharmacy_unit' => 'Main Pharmacy']);
        LabTechnician::create(['technician_id' => 'TEC0001', 'staff_id' => 'STF0006', 'laboratory_id' => 'LABO001', 'skill_area' => 'Hematology']);

        Patient::create(['patient_id' => 'PAT0001', 'first_name' => 'John', 'last_name' => 'Doe', 'gender' => 'male', 'date_of_birth' => '1988-04-12', 'phone_number' => '0123456789', 'email' => 'john@example.test', 'blood_type' => 'O+']);
        Patient::create(['patient_id' => 'PAT0002', 'first_name' => 'Mary', 'last_name' => 'Smith', 'gender' => 'female', 'date_of_birth' => '1995-09-30', 'phone_number' => '0987654321', 'email' => 'mary@example.test', 'blood_type' => 'A-']);

        Medicine::create(['medicine_id' => 'MED0001', 'medicine_name' => 'Paracetamol 500mg', 'medicine_type' => 'Tablet', 'manufacturer' => 'Acme Pharma', 'unit_price' => 0.10, 'stock_quantity' => 500]);
        Medicine::create(['medicine_id' => 'MED0002', 'medicine_name' => 'Amoxicillin 250mg', 'medicine_type' => 'Capsule', 'manufacturer' => 'Beta Labs', 'unit_price' => 0.25, 'stock_quantity' => 15]);
        Medicine::create(['medicine_id' => 'MED0003', 'medicine_name' => 'Warfarin 5mg', 'medicine_type' => 'Tablet', 'manufacturer' => 'Acme Pharma', 'unit_price' => 0.30, 'stock_quantity' => 200]);
        Medicine::create(['medicine_id' => 'MED0004', 'medicine_name' => 'Cephalexin 500mg', 'medicine_type' => 'Capsule', 'manufacturer' => 'Beta Labs', 'unit_price' => 0.28, 'stock_quantity' => 300]);
        MedicineBatch::create(['batch_id' => 'BAT0001', 'medicine_id' => 'MED0001', 'batch_number' => 'B-2024-01', 'manufacture_date' => '2024-01-01', 'expiry_date' => now()->addDays(20)->toDateString(), 'quantity' => 500]);

        DrugInteraction::create(['interaction_id' => 'DRI0001', 'medicine_id_1' => 'MED0001', 'medicine_id_2' => 'MED0003', 'interaction_effect' => 'Increased risk of bleeding with prolonged concurrent use', 'severity' => 'medium']);
        DrugSubstitution::create(['substitution_id' => 'DRS0001', 'original_medicine_id' => 'MED0002', 'alternative_medicine_id' => 'MED0004', 'reason' => 'Alternative antibiotic when out of stock or contraindicated (penicillin allergy)']);

        Appointment::create(['appointment_id' => 'APT0001', 'patient_id' => 'PAT0001', 'doctor_id' => 'DOC0001', 'booked_by' => 'STF0004', 'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '09:30', 'reason' => 'Chest pain follow-up']);

        Bill::create(['bill_id' => 'BIL0001', 'patient_id' => 'PAT0001', 'appointment_id' => 'APT0001', 'generated_by' => 'STF0004']);
        BillItem::create(['bill_item_id' => 'BI00001', 'bill_id' => 'BIL0001', 'item_type' => 'service', 'description' => 'Cardiology consultation', 'quantity' => 1, 'unit_price' => 25.00]);
        BillItem::create(['bill_item_id' => 'BI00002', 'bill_id' => 'BIL0001', 'item_type' => 'medicine', 'description' => 'Paracetamol 500mg x20', 'quantity' => 20, 'unit_price' => 0.10]);
        Bill::find('BIL0001')->recomputeTotal();

        $this->command->info('Seeded. Demo logins (password: Password123!):');
        foreach ($people as [$sid, $f, $l, $role, $email]) {
            $this->command->line("  - $email  ($role)");
        }
    }
}
