<?php

namespace App\DTOs;

class AppointmentDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $appointment_id = null,
        public ?string $patient_id = null,
        public ?string $doctor_id = null,
        public ?string $booked_by = null,
        public ?string $appointment_date = null,
        public ?string $appointment_time = null,
        public ?string $reason = null,
        public ?string $status = null,
        public ?string $cancellation_reason = null,
        public ?string $created_at = null,
        public ?string $updated_at = null,
        public ?PatientDTO $patient = null,
        public ?NamedEntity $doctor = null,
        public ?NamedEntity $bookedByStaff = null,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            appointment_id: $a['appointment_id'] ?? null,
            patient_id: $a['patient_id'] ?? null,
            doctor_id: $a['doctor_id'] ?? null,
            booked_by: $a['booked_by'] ?? null,
            appointment_date: $a['appointment_date'] ?? null,
            appointment_time: $a['appointment_time'] ?? null,
            reason: $a['reason'] ?? null,
            status: $a['status'] ?? null,
            cancellation_reason: $a['cancellation_reason'] ?? null,
            created_at: $a['created_at'] ?? null,
            updated_at: $a['updated_at'] ?? null,
            patient: isset($a['patient']) ? PatientDTO::fromArray($a['patient']) : null,
            doctor: isset($a['doctor_name']) ? new NamedEntity($a['doctor_name']) : null,
            bookedByStaff: isset($a['booked_by_name']) ? new NamedEntity($a['booked_by_name']) : null,
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
