<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_follow_up_flows', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_default');
        });

        DB::table('dashed__popup_follow_up_flows')->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('dashed__popup_follow_up_flows', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
