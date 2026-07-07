<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class LabTechnician extends Model
{
    use HasBusinessKey;

    protected $table = 'lab_technician';
    protected $primaryKey = 'technician_id';
    public string $idPrefix = 'TEC';
    public $timestamps = false;
    protected $guarded = [];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function name(): string
    {
        return $this->staff ? $this->staff->fullName() : $this->technician_id;
    }
}
