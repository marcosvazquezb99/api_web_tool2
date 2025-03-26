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
        'url',
        'description',
        'active',
        'last_time',
        'channel_id',
        'internal_id',
    ];

    protected $primaryKey = 'id';

}
