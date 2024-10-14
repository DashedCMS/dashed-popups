<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dashed__form_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_id')
                ->constrained('dashed__forms')
                ->cascadeOnDelete();
            $table->json('name');
            $table->boolean('required')
                ->default(false);
            $table->json('placeholder')
                ->nullable();
            $table->json('description')
                ->nullable();
            $table->json('helper_text');
            $table->string('type')
                ->default('input');
            $table->string('input_type')
                ->default('text');
            $table->json('options')
                ->nullable();
            $table->integer('sort')
                ->default(1);
            $table->json('images')
                ->nullable();
            $table->boolean('stack_start')
                ->default(0);
            $table->boolean('stack_end')
                ->default(0);

            $table->timestamps();
            $table->softDeletes();
        });


        Schema::table('dashed__form_inputs', function (Blueprint $table) {
            $table->longText('content')
                ->nullable()
                ->change();
        });

        Schema::create('dashed__form_input_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_input_id')
                ->constrained('dashed__form_inputs')
                ->cascadeOnDelete();

            $table->foreignId('form_field_id')
                ->constrained('dashed__form_fields')
                ->cascadeOnDelete();

            $table->longText('value');

            $table->timestamps();
        });

        Schema::table('dashed__forms', function (Blueprint $table) {
            $table->foreignId('email_confirmation_form_field_id')
                ->nullable()
                ->constrained('dashed__form_fields')
                ->nullOnDelete();
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
    }
};
