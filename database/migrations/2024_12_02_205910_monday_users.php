<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MondayUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //create new field on users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('monday_user_id')->nullable();
            $table->string('slack_user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //drop the field on users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monday_user_id');
            $table->dropColumn('slack_user_id');
        });
    }
}
