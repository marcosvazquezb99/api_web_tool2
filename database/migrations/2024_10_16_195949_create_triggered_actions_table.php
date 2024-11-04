<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTriggeredActionsTable extends Migration
{
    public function up()
    {
        Schema::create('triggered_actions', function (Blueprint $table) {
            $table->id('triggered_action_id');
            $table->foreignId('action_id')->constrained('actions', 'action_id')->onDelete('cascade');
            $table->foreignId('client_service_id')->constrained('clients_services', 'client_service_id')->onDelete('cascade');
            $table->timestamp('execution_date')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('triggered_actions');
    }
}
