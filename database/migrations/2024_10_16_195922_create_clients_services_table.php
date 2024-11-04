<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientsServicesTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('clients_services');
        Schema::create('clients_services', function (Blueprint $table) {
            $table->id('client_service_id');
            $table->foreignId('client_id')->constrained('clients', 'client_id')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services', 'service_id')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans', 'plan_id')->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained('employees', 'employee_id')->onDelete('set null');
            $table->timestamp('contract_date')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients_services');
    }
}
