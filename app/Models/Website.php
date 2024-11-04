<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'username',
        'password',
        'db_name',
        'db_username',
        'db_password',
        'wp_version',
        'install_path',
        'ssl_enabled',
        'client_id',
        'server_id',
    ];

    protected $table = 'websites';
}
