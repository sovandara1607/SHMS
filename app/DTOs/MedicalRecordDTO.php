<?php

namespace App\DTOs;

class MedicalRecordDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $medical_record_id = null,
        public ?string $patient_id = null,
        public ?string $doctor_id = null,
        public ?string $appointment_id = null,
        public ?string $symptoms = null,
        public ?string $diagnosis = null,
        public ?string $treatment_notes = null,
        public ?string $created_by = null,
        public ?string $created_at = null,
        public ?PatientDTO $patient = null,
        public ?NamedEntity $doctor = null,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            medical_record_id: $a['medical_record_id'] ?? null,
            patient_id: $a['patient_id'] ?? null,
            doctor_id: $a['doctor_id'] ?? null,
            appointment_id: $a['appointment_id'] ?? null,
            symptoms: $a['symptoms'] ?? null,
            diagnosis: $a['diagnosis'] ?? null,
            treatment_notes: $a['treatment_notes'] ?? null,
            created_by: $a['created_by'] ?? null,
            created_at: $a['created_at'] ?? null,
            patient: isset($a['patient']) ? PatientDTO::fromArray($a['patient']) : null,
            doctor: isset($a['doctor_name']) ? new NamedEntity($a['doctor_name']) : null,
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
