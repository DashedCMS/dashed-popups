<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFormInputTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dashed__forms', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dashed__form_inputs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_id')->constrained('dashed__forms');
            $table->ipAddress('ip');
            $table->text('user_agent');
            $table->longText('content');
            $table->string('from_url');
            $table->string('site_id');
            $table->string('locale');
            $table->boolean('viewed')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('form_input_tables');
    }
}
