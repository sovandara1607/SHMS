<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasBusinessKey;

    protected $table = 'medicine';
    protected $primaryKey = 'medicine_id';
    public string $idPrefix = 'MED';
    public $timestamps = false;
    protected $guarded = [];

    public function batches()
    {
        return $this->hasMany(MedicineBatch::class, 'medicine_id', 'medicine_id');
    }
}
