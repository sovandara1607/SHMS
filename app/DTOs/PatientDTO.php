<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class PatientDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $patient_id = null,
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $gender = null,
        public ?string $date_of_birth = null,
        public ?string $phone_number = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?string $blood_type = null,
        public ?string $allergy = null,
        public ?string $emergency_contact_name = null,
        public ?string $emergency_contact_phone = null,
        public ?string $patient_status = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?string $insurance_provider = null,
        public Collection $insurance = new Collection(),
        public Collection $doctorAssignments = new Collection(),
        public Collection $nurseAssignments = new Collection(),
        public Collection $adjustments = new Collection(),
        // Not yet migrated (Waves 2/4/6) — populated by the controller from
        // local Eloquent queries, kept as plain properties so the existing
        // Blade tabs (Appointments/Room Assignments/Medical Records/Billing)
        // need zero changes until their own waves land.
        public Collection $appointments = new Collection(),
        public Collection $roomAssignments = new Collection(),
        public Collection $medicalRecords = new Collection(),
        public Collection $bills = new Collection(),
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            patient_id: $a['patient_id'] ?? null,
            first_name: $a['first_name'] ?? null,
            last_name: $a['last_name'] ?? null,
            gender: $a['gender'] ?? null,
            date_of_birth: $a['date_of_birth'] ?? null,
            phone_number: $a['phone_number'] ?? null,
            email: $a['email'] ?? null,
            address: $a['address'] ?? null,
            blood_type: $a['blood_type'] ?? null,
            allergy: $a['allergy'] ?? null,
            emergency_contact_name: $a['emergency_contact_name'] ?? null,
            emergency_contact_phone: $a['emergency_contact_phone'] ?? null,
            patient_status: $a['patient_status'] ?? null,
            created_at: $a['created_at'] ?? null,
            updated_at: $a['updated_at'] ?? null,
            insurance_provider: $a['insurance_provider'] ?? null,
            insurance: collect($a['insurance'] ?? [])->map(fn (array $i) => PatientInsuranceDTO::fromArray($i)),
            doctorAssignments: collect($a['doctor_assignments'] ?? [])->map(fn (array $d) => DoctorAssignmentDTO::fromArray($d)),
            nurseAssignments: collect($a['nurse_assignments'] ?? [])->map(fn (array $n) => NurseAssignmentDTO::fromArray($n)),
            adjustments: collect($a['adjustments'] ?? [])->map(fn (array $adj) => self::adjustmentWithChanges($adj)),
        );
    }

    private const ADJUSTABLE_FIELD_LABELS = [
        'first_name' => 'First Name', 'last_name' => 'Last Name', 'gender' => 'Gender',
        'date_of_birth' => 'DOB', 'phone_number' => 'Phone', 'email' => 'Email',
        'address' => 'Address', 'blood_type' => 'Blood Type', 'allergy' => 'Allergy',
        'emergency_contact_name' => 'Contact Name', 'emergency_contact_phone' => 'Contact Phone',
        'patient_status' => 'Status',
    ];

    private static function adjustmentWithChanges(array $adj): object
    {
        $obj = (object) $adj;
        $obj->changes = collect(self::ADJUSTABLE_FIELD_LABELS)
            ->filter(fn ($label, $field) => filled($adj[$field] ?? null))
            ->map(fn ($label, $field) => "{$label}: {$adj[$field]}")
            ->values();

        return $obj;
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }

    public function toJson($options = 0): string
    {
        return json_encode($this, $options);
    }
}
