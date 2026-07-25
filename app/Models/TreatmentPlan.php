<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class TreatmentPlan extends Model
{
    use HasBusinessKey;

    protected $table = 'treatment_plan';
    protected $primaryKey = 'treatment_plan_id';
    public string $idPrefix = 'TP';
    public $timestamps = false;
    protected $guarded = [];

    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id', 'medical_record_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'doctor_id');
    }
}
