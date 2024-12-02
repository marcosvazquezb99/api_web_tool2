<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WpMigration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Create a table which will store de ftp of accesto to the wordpress to migrate
        Schema::create('wp_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('source_domain');
            $table->string('source_user');
            $table->string('source_password');
            $table->string('source_port');
            $table->string('destination_domain');
            $table->string('destination_user');
            $table->string('destination_password');
            $table->string('destination_port');
            $table->string('wordpress_url');
            $table->string('wordpress_username');
            $table->string('wordpress_password');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
