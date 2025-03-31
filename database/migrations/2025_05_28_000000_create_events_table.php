<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->string('location')->nullable();
            $table->string('source')->comment('Fuente o sistema de origen del evento');
            $table->string('external_id')->nullable()->comment('ID del evento en el sistema de origen');
            $table->string('category')->nullable();
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
            $table->json('additional_data')->nullable()->comment('Datos adicionales específicos de la fuente');
            $table->timestamps();

            // Índices para mejorar el rendimiento
            $table->index('source');
            $table->index('start_date');
            $table->index(['source', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('events');
    }
}
