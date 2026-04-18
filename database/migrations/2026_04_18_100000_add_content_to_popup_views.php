<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->json('content')->nullable()->after('discount_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
