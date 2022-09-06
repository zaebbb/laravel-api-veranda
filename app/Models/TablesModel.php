<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TablesModel extends Model
{
    use HasFactory;

    protected $fillable = [
        "name_table"
    ];
}
