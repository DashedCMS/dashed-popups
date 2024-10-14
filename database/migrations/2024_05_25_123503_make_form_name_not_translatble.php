<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (\Dashed\DashedForms\Models\Form::all() as $form) {
            $formName = json_decode($form->name, true);
            if ($formName) {
                $form->name = $formName[array_key_first($formName)];
                $form->save();
            }
        }

        Schema::table('dashed__forms', function (Blueprint $table) {
            $table->string('name')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            //
        });
    }
};
