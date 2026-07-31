<?php

namespace App\DTOs;

class PatientInsuranceDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $insurance_id = null,
        public ?string $patient_id = null,
        public ?string $insurance_provider = null,
        public ?string $policy_number = null,
        public ?string $coverage_details = null,
        public ?string $start_date = null,
        public ?string $end_date = null,
        public ?string $status = null,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            insurance_id: $a['insurance_id'] ?? null,
            patient_id: $a['patient_id'] ?? null,
            insurance_provider: $a['insurance_provider'] ?? null,
            policy_number: $a['policy_number'] ?? null,
            coverage_details: $a['coverage_details'] ?? null,
            start_date: $a['start_date'] ?? null,
            end_date: $a['end_date'] ?? null,
            status: $a['status'] ?? null,
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
