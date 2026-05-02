<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->unsignedBigInteger('matched_order_id')->nullable()->after('discount_code_id');
            $table->index(['popup_id', 'matched_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->dropIndex(['popup_id', 'matched_order_id']);
            $table->dropColumn('matched_order_id');
        });
    }
};
