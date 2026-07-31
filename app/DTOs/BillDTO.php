<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class BillDTO implements \JsonSerializable
{
    public function __construct(
        public ?string $bill_id = null,
        public ?string $patient_id = null,
        public ?string $appointment_id = null,
        public ?string $medical_record_id = null,
        public ?string $generated_by = null,
        public ?string $bill_date = null,
        public float|string|null $total_amount = null,
        public ?string $status = null,
        public ?PatientDTO $patient = null,
        public Collection $items = new Collection(),
        public Collection $payments = new Collection(),
        public float|string|null $paid_amount = null,
        public float|string|null $balance = null,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            bill_id: $a['bill_id'] ?? null,
            patient_id: $a['patient_id'] ?? null,
            appointment_id: $a['appointment_id'] ?? null,
            medical_record_id: $a['medical_record_id'] ?? null,
            generated_by: $a['generated_by'] ?? null,
            bill_date: $a['bill_date'] ?? null,
            total_amount: $a['total_amount'] ?? null,
            status: $a['status'] ?? null,
            patient: isset($a['patient']) ? PatientDTO::fromArray($a['patient']) : null,
            items: collect($a['items'] ?? [])->map(fn (array $i) => (object) $i),
            payments: collect($a['payments'] ?? [])->map(fn (array $p) => (object) $p),
            paid_amount: $a['paid_amount'] ?? null,
            balance: $a['balance'] ?? null,
        );
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
