<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'internal_id',
        'holded_id',
        'business_name',
    ];
    protected $primaryKey = 'client_id';

    protected $table = 'clients';
}
