<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDelivererAgripFormatterRangesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deliverer_agrip_formatter_ranges', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_uuid', 36)->index();
            $table->string('uuid', 36)->index();
            $table->string('formatter_uuid', 36)->index();
            $table->string('range', 64);
            $table->mediumText('actions');
            $table->timestamps();
            $table->unique(['owner_uuid','formatter_uuid', 'range'], 'delivery_agrip_formatter_ranges');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deliverer_agrip_formatter_ranges');
    }
}
