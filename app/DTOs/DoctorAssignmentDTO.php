<?php

namespace App\DTOs;

class DoctorAssignmentDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $assignment_id = null,
        public ?string $patient_id = null,
        public ?string $doctor_id = null,
        public ?string $role = null,
        public ?string $status = null,
        public ?string $assigned_at = null,
        public ?string $ended_at = null,
        public ?NamedEntity $doctor = null,
        public ?NamedEntity $assignedByStaff = null,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            assignment_id: $a['assignment_id'] ?? null,
            patient_id: $a['patient_id'] ?? null,
            doctor_id: $a['doctor_id'] ?? null,
            role: $a['role'] ?? null,
            status: $a['status'] ?? null,
            assigned_at: $a['assigned_at'] ?? null,
            ended_at: $a['ended_at'] ?? null,
            doctor: isset($a['doctor_name']) ? new NamedEntity($a['doctor_name']) : null,
            assignedByStaff: isset($a['assigned_by_name']) ? new NamedEntity($a['assigned_by_name']) : null,
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
