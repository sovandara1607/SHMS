<?php

namespace App\DTOs;

class NurseAssignmentDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $assignment_id = null,
        public ?string $patient_id = null,
        public ?string $nurse_id = null,
        public ?string $shift_id = null,
        public ?string $status = null,
        public ?string $assigned_at = null,
        public ?string $ended_at = null,
        public ?NamedEntity $nurse = null,
        public ?NamedEntity $assignedByStaff = null,
        public ?object $shift = null,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            assignment_id: $a['assignment_id'] ?? null,
            patient_id: $a['patient_id'] ?? null,
            nurse_id: $a['nurse_id'] ?? null,
            shift_id: $a['shift_id'] ?? null,
            status: $a['status'] ?? null,
            assigned_at: $a['assigned_at'] ?? null,
            ended_at: $a['ended_at'] ?? null,
            nurse: isset($a['nurse_name']) ? new NamedEntity($a['nurse_name']) : null,
            assignedByStaff: isset($a['assigned_by_name']) ? new NamedEntity($a['assigned_by_name']) : null,
            shift: $a['shift'] ?? null ? (object) $a['shift'] : null,
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
