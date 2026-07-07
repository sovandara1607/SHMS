<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class MedicalProcedure extends Model
{
    use HasBusinessKey;

    protected $table = 'medical_procedure';
    protected $primaryKey = 'procedure_id';
    public string $idPrefix = 'PRC';
    public $timestamps = false;
    protected $guarded = [];
}
