<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlackChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'slack_channel_name',
        'monday_board_id',
    ];
    protected $casts = [
        'id' => 'string',
        'monday_board_id' => 'string',
    ];

    protected $table = 'slack_channels';
}
