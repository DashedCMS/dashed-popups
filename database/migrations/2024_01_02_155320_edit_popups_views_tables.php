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
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->text('user_agent')
                ->nullable()
                ->change();
            $table->string('session_id')
                ->nullable()
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            //
        });
    }
};
