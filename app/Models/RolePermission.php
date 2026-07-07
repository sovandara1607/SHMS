<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Editable per-role capability grants (Roles & Permissions admin screen). */
class RolePermission extends Model
{
    protected $table = 'role_permissions';
    protected $guarded = [];
}
