<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class DrugSubstitution extends Model
{
    use HasBusinessKey;

    protected $table = 'drug_substitution';
    protected $primaryKey = 'substitution_id';
    public string $idPrefix = 'DRS';
    public $timestamps = false;
    protected $guarded = [];
}
