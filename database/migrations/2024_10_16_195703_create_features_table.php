<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFeaturesTable extends Migration
{
    public function up()
    {
        Schema::create('feature', function (Blueprint $table) {
            $table->id('feature_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('plan_id')->constrained('plans', 'plan_id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('caracteristicas');
    }
}
