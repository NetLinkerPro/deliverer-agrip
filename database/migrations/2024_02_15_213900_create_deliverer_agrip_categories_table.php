<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Ramsey\Uuid\Uuid;

class CreateDelivererAgripCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deliverer_agrip_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_uuid', 36)->index();
            $table->string('uuid', 36)->index();
            $table->string('name')->index();
            $table->string('description')->nullable();
            $table->boolean('active')->index();
            $table->text('uri')->nullable();
            $table->string('ctx')->nullable();
            $table->string('ctr')->nullable();
            $table->string('item_id')->index();
            $table->string('t')->nullable();
            $table->integer('table_number');
            $table->json('data')->nullable();
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
        Schema::dropIfExists('deliverer_agrip_categories');
    }
}
