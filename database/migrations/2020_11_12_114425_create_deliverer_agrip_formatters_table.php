<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDelivererAgripFormattersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('deliverer_agrip_formatters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_uuid', 36)->index();
            $table->string('uuid', 36)->index();

            $table->string('name', 64);
            $table->string('description')->nullable();

            $table->string('identifier_type', 15);
            $table->string('name_lang', 8);
            $table->string('name_type', 15);
            $table->string('url_type', 15);
            $table->string('price_currency', 15);
            $table->string('price_type', 15);
            $table->string('tax_country', 36);
            $table->string('stock_type', 15);
            $table->string('category_lang', 8);
            $table->string('category_type', 15);
            $table->string('image_lang', 8);
            $table->string('image_type', 15);
            $table->string('description_lang', 8);
            $table->string('description_type', 15);
            $table->string('attribute_lang', 8);
            $table->string('attribute_type', 15);

            $table->timestamps();

            $table->unique(['owner_uuid','name'], 'delivery_agrip_formatters');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('deliverer_agrip_formatters');
    }
}
