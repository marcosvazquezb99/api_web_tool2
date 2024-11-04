<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TriggeredAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_id',
        'client_service_id',
        'execution_date',
        'status',
    ];

    protected $table = 'triggered_actions';
}
