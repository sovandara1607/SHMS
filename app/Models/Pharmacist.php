<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Pharmacist extends Model
{
    use HasBusinessKey;

    protected $table = 'pharmacist';
    protected $primaryKey = 'pharmacist_id';
    public string $idPrefix = 'PHA';
    public $timestamps = false;
    protected $guarded = [];
}
