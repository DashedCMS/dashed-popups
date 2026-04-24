<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->enum('visibility_mode', ['everywhere', 'only_selection'])
                ->default('everywhere')
                ->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->dropColumn('visibility_mode');
        });
    }
};
