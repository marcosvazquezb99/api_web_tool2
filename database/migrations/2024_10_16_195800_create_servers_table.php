<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServersTable extends Migration
{
    public function up()
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id('server_id');
            $table->string('name');
            $table->string('ip');
            $table->integer('max_sites');
            $table->string('url')->nullable();
            $table->string('token')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('system');  // Example: 'Plesk'
            $table->timestamps();
        });

    }

    public function down()
    {
        Schema::dropIfExists('servers');
    }
}
