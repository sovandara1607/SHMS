<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasBusinessKey;

    protected $table = 'staff';
    protected $primaryKey = 'staff_id';
    public string $idPrefix = 'STF';
    protected $guarded = [];

    public function user()
    {
        return $this->hasOne(User::class, 'staff_id', 'staff_id');
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class, 'staff_id', 'staff_id');
    }

    public function nurse()
    {
        return $this->hasOne(Nurse::class, 'staff_id', 'staff_id');
    }

    public function receptionist()
    {
        return $this->hasOne(Receptionist::class, 'staff_id', 'staff_id');
    }

    public function pharmacist()
    {
        return $this->hasOne(Pharmacist::class, 'staff_id', 'staff_id');
    }

    public function labTechnician()
    {
        return $this->hasOne(LabTechnician::class, 'staff_id', 'staff_id');
    }

    /** Only meaningful for roles whose subtype table carries no department_id of its own (receptionist, pharmacist, lab_technician, admin). */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
