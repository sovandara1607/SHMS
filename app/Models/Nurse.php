<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Nurse extends Model
{
    use HasBusinessKey;

    protected $table = 'nurse';
    protected $primaryKey = 'nurse_id';
    public string $idPrefix = 'NUR';
    public $timestamps = false;
    protected $guarded = [];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function name(): string
    {
        return $this->staff ? $this->staff->fullName() : $this->nurse_id;
    }
}
