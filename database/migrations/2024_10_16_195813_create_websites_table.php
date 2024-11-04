<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebsitesTable extends Migration
{
    public function up()
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id('website_id');
            $table->string('url');
            $table->string('username'); // WordPress admin username
            $table->string('password'); // WordPress admin password

            // Database credentials for WordPress
            $table->string('db_name');
            $table->string('db_username');
            $table->string('db_password');

            // Optional WordPress-related fields
            $table->string('wp_version')->nullable(); // Version of WordPress installed
            $table->string('install_path')->nullable(); // Installation directory on the server
            $table->boolean('ssl_enabled')->default(false); // Indicates if SSL is enabled

            $table->foreignId('client_id')->constrained('clients', 'client_id')->onDelete('cascade');
            $table->foreignId('server_id')->constrained('servers', 'server_id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sitios_web');
    }
}
