<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class MedicalReport extends Model
{
    use HasBusinessKey;

    protected $table = 'medical_report';
    protected $primaryKey = 'report_id';
    public string $idPrefix = 'REP';
    public $timestamps = false;
    protected $guarded = [];
}
