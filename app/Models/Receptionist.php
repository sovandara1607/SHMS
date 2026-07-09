<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Receptionist extends Model
{
    use HasBusinessKey;

    protected $table = 'receptionist';
    protected $primaryKey = 'receptionist_id';
    public string $idPrefix = 'REC';
    public $timestamps = false;
    protected $guarded = [];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
