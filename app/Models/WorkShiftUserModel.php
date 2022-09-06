<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkShiftUserModel extends Model
{
    use HasFactory;

    protected $fillable = [
        "work_shift_id",
        "user_id",
        "status",
    ];
}
