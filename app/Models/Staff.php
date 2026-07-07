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

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
