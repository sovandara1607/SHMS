<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Single-row hospital-wide configuration (see Settings screen). */
class HospitalSetting extends Model
{
    protected $table = 'hospital_settings';
    protected $guarded = [];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
