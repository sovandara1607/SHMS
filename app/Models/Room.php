<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasBusinessKey;

    protected $table = 'room';
    protected $primaryKey = 'room_id';
    public string $idPrefix = 'RM';
    public $timestamps = false;
    protected $guarded = [];
}
