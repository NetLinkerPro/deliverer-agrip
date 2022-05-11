<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Ramsey\Uuid\Uuid;

class CreateDelivererAgripConfigurationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('deliverer_agrip_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_uuid', 36)->index();
            $table->string('uuid', 36)->index();
            $table->string('name');
            $table->string('url_1')->nullable();
            $table->string('url_2')->nullable();
            $table->string('login')->nullable();
            $table->string('pass')->nullable();
            $table->string('login2')->nullable();
            $table->string('pass2')->nullable();
            $table->text('token')->nullable();
            $table->boolean('debug')->nullable();
            $table->json('baselinker')->nullable();
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
        Schema::dropIfExists('deliverer_agrip_configurations');
    }
}
