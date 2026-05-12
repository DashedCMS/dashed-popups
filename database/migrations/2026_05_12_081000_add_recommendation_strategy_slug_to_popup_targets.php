<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__popup_targets')) {
            return;
        }
        if (Schema::hasColumn('dashed__popup_targets', 'recommendation_strategy_slug')) {
            return;
        }

        Schema::table('dashed__popup_targets', function (Blueprint $table) {
            // Used when match_type = 'recommendation_strategy': identifies which
            // RecommendationStrategy (by key()) selects the products this
            // popup shows.
            $table->string('recommendation_strategy_slug')->nullable()->after('targetable_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__popup_targets', 'recommendation_strategy_slug')) {
            return;
        }

        Schema::table('dashed__popup_targets', function (Blueprint $table) {
            $table->dropColumn('recommendation_strategy_slug');
        });
    }
};
