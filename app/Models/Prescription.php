<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasBusinessKey;

    protected $table = 'prescription';
    protected $primaryKey = 'prescription_id';
    public string $idPrefix = 'PRS';
    public $timestamps = false;
    protected $guarded = [];
}
