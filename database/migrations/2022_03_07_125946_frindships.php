<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Frindships extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('frindships', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('accept')->default(0);
            $table->boolean('is_read')->default(0);

            $table->bigInteger('fisrt_user_id')->unsigned();
            $table->foreign('fisrt_user_id')->references('id')->on('users');

            $table->bigInteger('second_user_id')->unsigned();
            $table->foreign('second_user_id')->references('id')->on('users');

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
        //
        Schema::dropIfExists('frindships');
    }
}
