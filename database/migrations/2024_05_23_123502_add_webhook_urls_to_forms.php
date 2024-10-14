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
        Schema::table('dashed__forms', function (Blueprint $table) {
            $table->text('webhook_url')
                ->nullable();
            $table->string('webhook_class')
                ->nullable();
        });

        Schema::table('dashed__form_inputs', function (Blueprint $table) {
            $table->boolean('should_send_webhook')
                ->default(false)
                ->after('viewed');
            $table->boolean('webhook_send')
                ->default(false)
                ->after('should_send_webhook');
            $table->string('webhook_error')
                ->nullable()
                ->after('webhook_send');
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
