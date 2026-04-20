<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->string('device_type', 10)->nullable()->after('user_agent');
            $table->string('url', 500)->nullable()->after('device_type');
            $table->string('referrer', 500)->nullable()->after('url');
            $table->string('locale', 10)->nullable()->after('referrer');
            $table->string('triggered_by', 20)->nullable()->after('locale');
            $table->index(['popup_id', 'device_type']);
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->dropIndex(['popup_id', 'device_type']);
            $table->dropColumn(['device_type', 'url', 'referrer', 'locale', 'triggered_by']);
        });
    }
};
