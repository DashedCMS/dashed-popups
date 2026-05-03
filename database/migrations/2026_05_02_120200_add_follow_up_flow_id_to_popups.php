<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->unsignedBigInteger('follow_up_flow_id')->nullable()->after('api_subscriptions');
            $table->index('follow_up_flow_id', 'popups_follow_up_flow_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->dropIndex('popups_follow_up_flow_id_idx');
            $table->dropColumn('follow_up_flow_id');
        });
    }
};
