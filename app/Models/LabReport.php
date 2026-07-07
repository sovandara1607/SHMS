<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class LabReport extends Model
{
    use HasBusinessKey;

    protected $table = 'lab_report';
    protected $primaryKey = 'lab_report_id';
    public string $idPrefix = 'LR';
    public $timestamps = false;
    protected $guarded = [];
}
