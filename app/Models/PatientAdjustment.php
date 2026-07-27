<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class PatientAdjustment extends Model
{
    use HasBusinessKey;

    protected $table = 'patient_adjustment';
    protected $primaryKey = 'adjustment_id';
    public string $idPrefix = 'PADJ';
    public $timestamps = false;
    protected $guarded = [];
}
