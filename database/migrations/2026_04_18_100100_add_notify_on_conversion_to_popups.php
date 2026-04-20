<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->boolean('notify_on_conversion')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->dropColumn('notify_on_conversion');
        });
    }
};
