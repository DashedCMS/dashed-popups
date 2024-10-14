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
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->id();

            $table->string('name')
                ->unique();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('delay')
                ->default(0);
            $table->integer('show_again_after')
                ->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            //
        });
    }
};
