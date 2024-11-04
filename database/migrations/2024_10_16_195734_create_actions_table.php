<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActionsTable extends Migration
{
    public function up()
    {
        Schema::create('actions', function (Blueprint $table) {
            $table->id('action_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('command_type');
            $table->string('associated_entity');  // Can be 'Services', 'Plans', 'Features'
            $table->unsignedBigInteger('entity_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('actions');
    }
}



