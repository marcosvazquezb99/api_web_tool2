<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientService extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'service_id',
        'plan_id',
        'employee_id',
        'contract_date',
    ];

    protected $table = 'clients_services';
}
