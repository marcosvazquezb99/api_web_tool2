<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boards extends Model
{
    use HasFactory;

    protected $table = 'boards';

    protected $fillable = [
        'id',
        'name',
        'description',
        'board_id',
        'active',
        'last_time',
    ];

    protected $primaryKey = 'board_id';

}
