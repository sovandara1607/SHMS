<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class DrugInteraction extends Model
{
    use HasBusinessKey;

    protected $table = 'drug_interaction';
    protected $primaryKey = 'interaction_id';
    public string $idPrefix = 'DRI';
    public $timestamps = false;
    protected $guarded = [];
}
