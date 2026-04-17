<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dashed__popups')
            ->where('delay', '>', 0)
            ->update([
                'trigger_type' => 'delay',
                'trigger_value' => DB::raw('delay'),
            ]);

        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->dropColumn('delay');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->integer('delay')->default(0)->after('end_date');
        });

        DB::table('dashed__popups')
            ->where('trigger_type', 'delay')
            ->update(['delay' => DB::raw('trigger_value')]);
    }
};
