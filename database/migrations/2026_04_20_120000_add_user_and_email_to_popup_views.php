<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('session_id');
            $table->string('email')->nullable()->after('user_id');

            $table->index(['popup_id', 'user_id']);
            $table->index(['popup_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->dropIndex(['popup_id', 'user_id']);
            $table->dropIndex(['popup_id', 'email']);
            $table->dropColumn(['user_id', 'email']);
        });
    }
};
