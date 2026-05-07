<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Voegt cached stats-kolommen toe aan dashed__popups zodat de PopupResource
 * overzichtspagina niet meer per popup een subquery hoeft te draaien.
 * Wordt ge-updated door dashed:recalculate-popup-stats (hourly scheduler).
 *
 *  - all-time tellers (views, submits, dismissals, in_flow)
 *  - 30-daagse tellers + bounces + revenue
 *  - stats_recalculated_at zodat de admin in de UI kan zien hoe vers de
 *    cijfers zijn.
 */
return new class () extends Migration {
    private string $table = 'dashed__popups';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            if (! Schema::hasColumn($this->table, 'cached_views_count')) {
                $table->unsignedInteger('cached_views_count')->default(0)->after('end_date');
            }
            if (! Schema::hasColumn($this->table, 'cached_submits_count')) {
                $table->unsignedInteger('cached_submits_count')->default(0)->after('cached_views_count');
            }
            if (! Schema::hasColumn($this->table, 'cached_dismissals_count')) {
                $table->unsignedInteger('cached_dismissals_count')->default(0)->after('cached_submits_count');
            }
            if (! Schema::hasColumn($this->table, 'cached_in_flow_count')) {
                $table->unsignedInteger('cached_in_flow_count')->default(0)->after('cached_dismissals_count');
            }
            if (! Schema::hasColumn($this->table, 'cached_views_30d')) {
                $table->unsignedInteger('cached_views_30d')->default(0)->after('cached_in_flow_count');
            }
            if (! Schema::hasColumn($this->table, 'cached_submits_30d')) {
                $table->unsignedInteger('cached_submits_30d')->default(0)->after('cached_views_30d');
            }
            if (! Schema::hasColumn($this->table, 'cached_dismissals_30d')) {
                $table->unsignedInteger('cached_dismissals_30d')->default(0)->after('cached_submits_30d');
            }
            if (! Schema::hasColumn($this->table, 'cached_bounces_30d')) {
                $table->unsignedInteger('cached_bounces_30d')->default(0)->after('cached_dismissals_30d');
            }
            if (! Schema::hasColumn($this->table, 'cached_revenue_30d')) {
                $table->decimal('cached_revenue_30d', 12, 2)->default(0)->after('cached_bounces_30d');
            }
            if (! Schema::hasColumn($this->table, 'stats_recalculated_at')) {
                $table->timestamp('stats_recalculated_at')->nullable()->after('cached_revenue_30d');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            foreach ([
                'cached_views_count',
                'cached_submits_count',
                'cached_dismissals_count',
                'cached_in_flow_count',
                'cached_views_30d',
                'cached_submits_30d',
                'cached_dismissals_30d',
                'cached_bounces_30d',
                'cached_revenue_30d',
                'stats_recalculated_at',
            ] as $col) {
                if (Schema::hasColumn($this->table, $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
