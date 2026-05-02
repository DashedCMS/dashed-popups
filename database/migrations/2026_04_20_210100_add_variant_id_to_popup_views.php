<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->foreignId('variant_id')
                ->nullable()
                ->after('popup_id')
                ->constrained('dashed__popup_variants')
                ->nullOnDelete();

            $table->index(['popup_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropIndex(['popup_id', 'variant_id']);
            $table->dropColumn('variant_id');
        });
    }
};
