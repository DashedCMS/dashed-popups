<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->timestamp('follow_up_started_at')->nullable()->after('newsletter_synced_at');
            $table->timestamp('follow_up_cancelled_at')->nullable()->after('follow_up_started_at');

            $table->index(
                ['email', 'follow_up_started_at', 'follow_up_cancelled_at'],
                'popup_views_followup_email_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->dropIndex('popup_views_followup_email_idx');
            $table->dropColumn(['follow_up_started_at', 'follow_up_cancelled_at']);
        });
    }
};
