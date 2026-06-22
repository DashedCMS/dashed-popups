<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__popups')) {
            return;
        }

        Schema::table('dashed__popups', function (Blueprint $table): void {
            if (! Schema::hasColumn('dashed__popups', 'discount_usage_limit')) {
                // Hoe vaak de gegenereerde kortingscode in totaal gebruikt mag worden.
                $table->integer('discount_usage_limit')->default(1)->after('discount_valid_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__popups')) {
            return;
        }

        Schema::table('dashed__popups', function (Blueprint $table): void {
            if (Schema::hasColumn('dashed__popups', 'discount_usage_limit')) {
                $table->dropColumn('discount_usage_limit');
            }
        });
    }
};
