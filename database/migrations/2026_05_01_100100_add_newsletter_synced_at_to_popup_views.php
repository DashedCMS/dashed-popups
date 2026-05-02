<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->timestamp('newsletter_synced_at')->nullable()->after('submitted_at');
            $table->index(['popup_id', 'newsletter_synced_at'], 'popup_views_pid_synced_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->dropIndex('popup_views_pid_synced_idx');
            $table->dropColumn('newsletter_synced_at');
        });
    }
};
