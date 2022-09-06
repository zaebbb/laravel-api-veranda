<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPositions extends Model
{
    use HasFactory;

    protected $hidden = [
        "created_at",
        "order_id",
        "updated_at",
    ];

    protected $fillable = [
        "order_id",
        "position",
        "count",
        "price",
    ];
}
