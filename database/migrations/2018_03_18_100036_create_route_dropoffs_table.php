<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRouteDropoffsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('route_dropoffs', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('route_id');
            $table->foreign('route_id')
                ->references('id')->on('routes')
                ->onDelete('cascade');
            $table->integer('step')->nullable();
            $table->double('latitube', 10, 7);
            $table->double('longitube', 10, 7);
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
        Schema::dropIfExists('route_dropoffs');
    }
}
