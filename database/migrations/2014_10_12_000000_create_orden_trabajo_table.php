<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdenTrabajoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */    
    
    public function up()
    {
        Schema::create('orden_trabajo', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('id_ot')->nullable();
            $table->integer('estado_orden_trabajo')->nullable();
            $table->string('geo')->nullable();
            $table->dateTime('fecha_hora_carga')->nullable();
            $table->dateTime('fecha_hora_validacion')->nullable();
            $table->dateTime('fecha_hora_envio')->nullable();
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
        Schema::dropIfExists('orden_trabajo');
    }
};
