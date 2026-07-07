<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasBusinessKey;

    protected $table = 'doctor';
    protected $primaryKey = 'doctor_id';
    public string $idPrefix = 'DOC';
    public $timestamps = false;
    protected $guarded = [];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'doctor_id', 'doctor_id');
    }

    public function name(): string
    {
        return $this->staff ? $this->staff->fullName() : $this->doctor_id;
    }
}
