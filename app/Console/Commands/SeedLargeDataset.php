<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\LabTechnician;
use App\Models\Medicine;
use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-generates a large, realistic dataset directly in Postgres so we can
 * verify the app (and the pg_trgm search indexes — see migration
 * 2026_01_02_000005) actually holds up at ~1M+ rows, not just the ~10-row
 * demo seed from DatabaseSeeder.
 *
 * Deliberately bypasses Eloquent::create(): HasBusinessKey looks up the
 * current max id with a query on every single insert, which is fine for a
 * handful of demo rows but would mean 1,000,000+ round trips here. Instead
 * this command computes sequential business keys in PHP and bulk-inserts
 * via the query builder in chunks.
 */
class SeedLargeDataset extends Command
{
    protected $signature = 'seed:large
        {--patients=1000000 : Number of patients to generate}
        {--appointments=200000 : Number of appointments to generate}
        {--medical-records=200000 : Number of medical records to generate}
        {--prescription-rate=0.3 : Fraction of medical records that also get a prescription (1-3 items)}
        {--lab-orders=200000 : Number of standalone lab test orders to generate}
        {--bills=200000 : Number of bills (with items + payments) to generate}
        {--chunk=2000 : Rows per bulk insert}';

    protected $description = 'Bulk-generate a large dataset (patients/appointments/medical records/prescriptions/lab orders/bills) for scale testing';

    private \Faker\Generator $faker;

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        $this->faker = \Faker\Factory::create();
        DB::connection()->disableQueryLog();

        $doctorIds = Doctor::pluck('doctor_id')->all();
        $staffIds = Staff::pluck('staff_id')->all();
        $technicianIds = LabTechnician::pluck('technician_id')->all();
        $medicineIds = Medicine::pluck('medicine_id')->all();

        if (empty($doctorIds) || empty($staffIds)) {
            $this->error('No doctors/staff found. Run `php artisan db:seed` first so there is at least one doctor and staff member to attach records to.');

            return self::FAILURE;
        }
        if (empty($medicineIds)) {
            $this->warn('No medicines found — prescriptions will be skipped. Run `php artisan db:seed` first for full coverage.');
        }
        if (empty($technicianIds)) {
            $this->warn('No lab technicians found — lab orders will be created unassigned.');
        }

        $patientCount = (int) $this->option('patients');
        $appointmentCount = (int) $this->option('appointments');
        $medicalRecordCount = (int) $this->option('medical-records');
        $prescriptionRate = (float) $this->option('prescription-rate');
        $labOrderCount = (int) $this->option('lab-orders');
        $billCount = (int) $this->option('bills');
        $chunkSize = (int) $this->option('chunk');

        $start = microtime(true);

        $firstPatientSeq = $this->nextSequence('patient', 'patient_id', 'PAT');
        $this->seedPatients($patientCount, $firstPatientSeq, $chunkSize);

        // Range of patient sequence numbers valid to link against: every
        // existing patient (1..firstPatientSeq-1) plus any just created.
        $firstLinkableSeq = 1;
        $lastLinkableSeq = $firstPatientSeq + $patientCount - 1;

        if ($lastLinkableSeq < $firstLinkableSeq) {
            $this->error('No patients exist to link against. Run with --patients > 0 first, or `php artisan db:seed`.');

            return self::FAILURE;
        }

        if ($appointmentCount > 0) {
            $this->seedAppointments($appointmentCount, $firstLinkableSeq, $lastLinkableSeq, $doctorIds, $staffIds, $chunkSize);
        }

        if ($medicalRecordCount > 0) {
            $this->seedMedicalRecords($medicalRecordCount, $firstLinkableSeq, $lastLinkableSeq, $doctorIds, $staffIds, $medicineIds, $prescriptionRate, $chunkSize);
        }

        if ($labOrderCount > 0) {
            $this->seedLabOrders($labOrderCount, $firstLinkableSeq, $lastLinkableSeq, $doctorIds, $technicianIds, $chunkSize);
        }

        if ($billCount > 0) {
            $this->seedBills($billCount, $firstLinkableSeq, $lastLinkableSeq, $staffIds, $chunkSize);
        }

        $elapsed = round(microtime(true) - $start, 1);
        $this->newLine();
        $this->info("Done in {$elapsed}s. Totals now:");
        foreach (['patient', 'appointment', 'medical_record', 'prescription', 'prescription_item', 'lab_test_order', 'lab_test_result', 'bill', 'bill_item', 'payment'] as $table) {
            $this->line('  ' . $table . ': ' . number_format(DB::table($table)->count()));
        }

        return self::SUCCESS;
    }

    /**
     * Next free sequence number for a prefixed business key (PAT0001 -> 1,
     * ...). Some existing IDs in this app aren't sequential at all — e.g.
     * LabController generates lab_test_result ids as 'LRS' . Str::random(8)
     * — so this only considers ids that are the prefix followed by digits
     * and nothing else, ignoring random-suffix ones instead of tripping
     * over them (a naive "longest/lexicographically-last id" scan picks a
     * random-suffix id as the max and derives a bogus/duplicate sequence).
     */
    private function nextSequence(string $table, string $column, string $prefix): int
    {
        // The start position is inlined (not bound) because a bound ? here
        // makes Postgres pick SUBSTRING's regex-pattern overload instead of
        // the start-position one (PDO sends params as untyped text, and
        // `SUBSTRING(text FROM text)` is a valid overload meaning "regex
        // match"), silently turning this into a pattern match on the
        // stringified position number instead of a substring extraction.
        // Safe to inline: it's strlen($prefix) + 1, never user input.
        $position = strlen($prefix) + 1;

        $max = DB::table($table)
            ->whereRaw("{$column} ~ ?", ['^' . $prefix . '[0-9]+$'])
            ->selectRaw("MAX(CAST(SUBSTRING({$column} FROM {$position}) AS INTEGER)) as max_seq")
            ->value('max_seq');

        return ((int) $max) + 1;
    }

    private function key(string $prefix, int $seq): string
    {
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function seedPatients(int $count, int $startSeq, int $chunkSize): void
    {
        $statuses = ['active', 'active', 'active', 'admitted', 'admitted', 'icu', 'discharged', 'inactive'];
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

        $bar = $this->output->createProgressBar($count);
        $bar->setMessage('patients');

        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $rows = [];
            $n = min($chunkSize, $count - $offset);

            for ($i = 0; $i < $n; $i++) {
                $seq = $startSeq + $offset + $i;
                $first = $this->faker->firstName();
                $last = $this->faker->lastName();
                $rows[] = [
                    'patient_id' => $this->key('PAT', $seq),
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $this->faker->randomElement(['male', 'female', 'other']),
                    'date_of_birth' => $this->faker->date('Y-m-d', '-1 year'),
                    'phone_number' => $this->faker->numerify('01#########'),
                    'email' => strtolower($first . '.' . $last . $seq . '@example.test'),
                    'address' => $this->faker->streetAddress(),
                    'blood_type' => $this->faker->randomElement($bloodTypes),
                    'allergy' => $this->faker->boolean(20) ? $this->faker->randomElement(['Penicillin', 'Latex', 'Sulfa', 'Peanuts']) : null,
                    'emergency_contact_name' => $this->faker->name(),
                    'emergency_contact_phone' => $this->faker->numerify('01#########'),
                    'patient_status' => $this->faker->randomElement($statuses),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::transaction(fn () => DB::table('patient')->insert($rows));
            $bar->advance($n);
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedAppointments(int $count, int $firstPatientSeq, int $lastPatientSeq, array $doctorIds, array $staffIds, int $chunkSize): void
    {
        $statuses = ['scheduled', 'scheduled', 'completed', 'completed', 'completed', 'cancelled'];
        $reasons = ['Follow-up', 'Routine checkup', 'Consultation', 'Post-op review', 'Annual physical', 'Symptom review'];
        $startSeq = $this->nextSequence('appointment', 'appointment_id', 'APT');

        $bar = $this->output->createProgressBar($count);
        $bar->setMessage('appointments');

        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $rows = [];
            $n = min($chunkSize, $count - $offset);

            for ($i = 0; $i < $n; $i++) {
                $seq = $startSeq + $offset + $i;
                $patientSeq = random_int($firstPatientSeq, $lastPatientSeq);
                $status = $this->faker->randomElement($statuses);
                $rows[] = [
                    'appointment_id' => $this->key('APT', $seq),
                    'patient_id' => $this->key('PAT', $patientSeq),
                    'doctor_id' => $this->faker->randomElement($doctorIds),
                    'booked_by' => $this->faker->randomElement($staffIds),
                    'appointment_date' => $this->faker->dateTimeBetween('-1 year', '+1 month')->format('Y-m-d'),
                    'appointment_time' => $this->faker->time('H:i:s'),
                    'reason' => $this->faker->randomElement($reasons),
                    'status' => $status,
                    'cancellation_reason' => $status === 'cancelled' ? 'Patient requested reschedule' : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::transaction(fn () => DB::table('appointment')->insert($rows));
            $bar->advance($n);
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedMedicalRecords(int $count, int $firstPatientSeq, int $lastPatientSeq, array $doctorIds, array $staffIds, array $medicineIds, float $prescriptionRate, int $chunkSize): void
    {
        $diagnoses = ['I10 - Essential Hypertension', 'E11.9 - Type 2 Diabetes', 'J20.9 - Acute Bronchitis', 'M54.5 - Low Back Pain', 'K21.9 - GERD', 'J45.909 - Asthma'];
        $dosages = ['250mg', '500mg', '10mg', '20mg', '5mg'];
        $frequencies = ['Once daily', 'Twice daily', 'Three times daily', 'Every 8 hours', 'As needed'];
        $durations = ['5 days', '7 days', '10 days', '14 days', '30 days'];

        $recordSeq = $this->nextSequence('medical_record', 'medical_record_id', 'MR');
        $prescriptionSeq = $this->nextSequence('prescription', 'prescription_id', 'PRS');
        $prescriptionItemSeq = $this->nextSequence('prescription_item', 'prescription_item_id', 'PI');
        $canPrescribe = ! empty($medicineIds) && $prescriptionRate > 0;

        $bar = $this->output->createProgressBar($count);
        $bar->setMessage('medical records');

        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $recordRows = [];
            $prescriptionRows = [];
            $prescriptionItemRows = [];
            $n = min($chunkSize, $count - $offset);

            for ($i = 0; $i < $n; $i++) {
                $seq = $recordSeq + $offset + $i;
                $patientSeq = random_int($firstPatientSeq, $lastPatientSeq);
                $patientId = $this->key('PAT', $patientSeq);
                $doctorId = $this->faker->randomElement($doctorIds);
                $recordId = $this->key('MR', $seq);

                $recordRows[] = [
                    'medical_record_id' => $recordId,
                    'patient_id' => $patientId,
                    'doctor_id' => $doctorId,
                    'symptoms' => $this->faker->sentence(6),
                    'diagnosis' => $this->faker->randomElement($diagnoses),
                    'treatment_notes' => $this->faker->sentence(10),
                    'created_by' => $this->faker->randomElement($staffIds),
                    'created_at' => now(),
                ];

                if ($canPrescribe && $this->faker->boolean((int) ($prescriptionRate * 100))) {
                    $pSeq = $prescriptionSeq++;
                    $prescriptionId = $this->key('PRS', $pSeq);
                    $prescriptionRows[] = [
                        'prescription_id' => $prescriptionId,
                        'medical_record_id' => $recordId,
                        'patient_id' => $patientId,
                        'doctor_id' => $doctorId,
                        'prescription_date' => now()->toDateString(),
                        'notes' => $this->faker->boolean(30) ? $this->faker->sentence(8) : null,
                    ];

                    foreach (range(1, random_int(1, 3)) as $_) {
                        $prescriptionItemRows[] = [
                            'prescription_item_id' => $this->key('PI', $prescriptionItemSeq++),
                            'prescription_id' => $prescriptionId,
                            'medicine_id' => $this->faker->randomElement($medicineIds),
                            'dosage' => $this->faker->randomElement($dosages),
                            'frequency' => $this->faker->randomElement($frequencies),
                            'duration' => $this->faker->randomElement($durations),
                            'usage_instruction' => $this->faker->boolean(50) ? 'Take with food' : null,
                            'quantity' => random_int(10, 60),
                        ];
                    }
                }
            }

            DB::transaction(function () use ($recordRows, $prescriptionRows, $prescriptionItemRows) {
                DB::table('medical_record')->insert($recordRows);
                if (! empty($prescriptionRows)) {
                    DB::table('prescription')->insert($prescriptionRows);
                }
                if (! empty($prescriptionItemRows)) {
                    DB::table('prescription_item')->insert($prescriptionItemRows);
                }
            });
            $bar->advance($n);
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedLabOrders(int $count, int $firstPatientSeq, int $lastPatientSeq, array $doctorIds, array $technicianIds, int $chunkSize): void
    {
        $testNames = ['Blood Test', 'Lipid Panel', 'CT Scan', 'X-ray', 'MRI', 'Urine Test', 'ECG', 'Cardiac Troponin', 'Iron Studies', 'Blood Culture'];
        $orderStatuses = ['pending', 'in_progress', 'completed', 'completed', 'completed', 'cancelled'];
        $resultStatuses = ['normal', 'normal', 'normal', 'abnormal'];
        $orderSeq = $this->nextSequence('lab_test_order', 'test_order_id', 'LAB');
        $resultSeq = $this->nextSequence('lab_test_result', 'test_result_id', 'LRS');

        $bar = $this->output->createProgressBar($count);
        $bar->setMessage('lab orders');

        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $orderRows = [];
            $resultRows = [];
            $n = min($chunkSize, $count - $offset);

            for ($i = 0; $i < $n; $i++) {
                $seq = $orderSeq + $offset + $i;
                $patientSeq = random_int($firstPatientSeq, $lastPatientSeq);
                $status = $this->faker->randomElement($orderStatuses);
                $orderId = $this->key('LAB', $seq);

                $orderRows[] = [
                    'test_order_id' => $orderId,
                    'patient_id' => $this->key('PAT', $patientSeq),
                    'doctor_id' => $this->faker->randomElement($doctorIds),
                    'technician_id' => ! empty($technicianIds) ? $this->faker->randomElement($technicianIds) : null,
                    'medical_record_id' => null,
                    'test_name' => $this->faker->randomElement($testNames),
                    'order_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
                    'status' => $status,
                ];

                if ($status === 'completed' && ! empty($technicianIds)) {
                    $resultRows[] = [
                        'test_result_id' => $this->key('LRS', $resultSeq++),
                        'test_order_id' => $orderId,
                        'result_value' => $this->faker->sentence(6),
                        'result_status' => $this->faker->randomElement($resultStatuses),
                        'remarks' => $this->faker->boolean(30) ? $this->faker->sentence(5) : null,
                        'entered_by' => $this->faker->randomElement($technicianIds),
                        'entered_at' => now(),
                    ];
                }
            }

            DB::transaction(function () use ($orderRows, $resultRows) {
                DB::table('lab_test_order')->insert($orderRows);
                if (! empty($resultRows)) {
                    DB::table('lab_test_result')->insert($resultRows);
                }
            });
            $bar->advance($n);
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedBills(int $count, int $firstPatientSeq, int $lastPatientSeq, array $staffIds, int $chunkSize): void
    {
        $itemTypes = ['service', 'medicine', 'lab_test', 'procedure', 'room'];
        $descriptions = [
            'service' => ['Consultation fee', 'Specialist consultation', 'Emergency visit'],
            'medicine' => ['Medication dispensing', 'Prescription refill'],
            'lab_test' => ['Blood test', 'Lab panel', 'Diagnostic imaging'],
            'procedure' => ['Minor procedure', 'Outpatient procedure'],
            'room' => ['Ward stay (per day)', 'ICU stay (per day)'],
        ];
        $paymentMethods = ['cash', 'card', 'online'];

        $billSeq = $this->nextSequence('bill', 'bill_id', 'BIL');
        $itemSeq = $this->nextSequence('bill_item', 'bill_item_id', 'BI');
        $paymentSeq = $this->nextSequence('payment', 'payment_id', 'PAY');

        $bar = $this->output->createProgressBar($count);
        $bar->setMessage('bills');

        for ($offset = 0; $offset < $count; $offset += $chunkSize) {
            $billRows = [];
            $itemRows = [];
            $paymentRows = [];
            $n = min($chunkSize, $count - $offset);

            for ($i = 0; $i < $n; $i++) {
                $seq = $billSeq + $offset + $i;
                $patientSeq = random_int($firstPatientSeq, $lastPatientSeq);
                $billId = $this->key('BIL', $seq);
                $generatedBy = $this->faker->randomElement($staffIds);

                $total = 0.0;
                foreach (range(1, random_int(1, 3)) as $_) {
                    $type = $this->faker->randomElement($itemTypes);
                    $quantity = random_int(1, 3);
                    $unitPrice = round($this->faker->randomFloat(2, 10, 800), 2);
                    $total += $quantity * $unitPrice;
                    $itemRows[] = [
                        'bill_item_id' => $this->key('BI', $itemSeq++),
                        'bill_id' => $billId,
                        'item_type' => $type,
                        'description' => $this->faker->randomElement($descriptions[$type]),
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        // subtotal is a Postgres STORED GENERATED column — do not insert it.
                    ];
                }
                $total = round($total, 2);

                $roll = $this->faker->numberBetween(1, 100);
                $status = $roll <= 40 ? 'unpaid' : ($roll <= 70 ? 'partially_paid' : 'paid');

                $billRows[] = [
                    'bill_id' => $billId,
                    'patient_id' => $this->key('PAT', $patientSeq),
                    'appointment_id' => null,
                    'generated_by' => $generatedBy,
                    'bill_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                    'total_amount' => $total,
                    'status' => $status,
                ];

                if ($status !== 'unpaid') {
                    $amountPaid = $status === 'paid' ? $total : round($total * $this->faker->randomFloat(2, 0.2, 0.8), 2);
                    $paymentRows[] = [
                        'payment_id' => $this->key('PAY', $paymentSeq++),
                        'bill_id' => $billId,
                        'received_by' => $generatedBy,
                        'payment_method' => $this->faker->randomElement($paymentMethods),
                        'amount_paid' => $amountPaid,
                        'payment_date' => now()->toDateString(),
                        'transaction_reference' => strtoupper($this->faker->bothify('TXN########')),
                    ];
                }
            }

            DB::transaction(function () use ($billRows, $itemRows, $paymentRows) {
                DB::table('bill')->insert($billRows);
                DB::table('bill_item')->insert($itemRows);
                if (! empty($paymentRows)) {
                    DB::table('payment')->insert($paymentRows);
                }
            });
            $bar->advance($n);
        }

        $bar->finish();
        $this->newLine();
    }
}
