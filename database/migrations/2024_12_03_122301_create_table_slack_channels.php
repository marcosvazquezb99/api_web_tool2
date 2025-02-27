<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableSlackChannels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('slack_channels');
        Schema::create('slack_channels', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('slack_channel_name');
            $table->timestamps();

            // Relación con boards
            $table->BigInteger('monday_board_id')->nullable();
            $table->foreign('monday_board_id')->references('id')->on('boards')->onDelete('cascade');
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('table_slack_channels');
    }
}
