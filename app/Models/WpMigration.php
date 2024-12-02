<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WpMigration extends Model
{
    use HasFactory;

    /**
     * @var mixed
     */
    public $source_domain;
    protected $table = 'wp_migrations';
    protected $fillable = [
        'source_domain',
        'source_user',
        'source_password',
        'source_port',
        'destination_domain',
        'destination_user',
        'destination_password',
        'destination_port',
        'wordpress_url',
        'wordpress_username',
        'wordpress_password',
    ];
}
