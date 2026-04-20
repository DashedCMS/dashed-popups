<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->json('ai_analysis')->nullable()->after('notify_on_conversion');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_analysis');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->dropColumn(['ai_analysis', 'ai_analyzed_at']);
        });
    }
};
