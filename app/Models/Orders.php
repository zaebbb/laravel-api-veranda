<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    use HasFactory;

    protected $fillable = [
        "work_shift_id",
        "table",
        "shift_worker",
        "status",
        "price",
        "number_of_person",
        "more_menu_info"
    ];
}
