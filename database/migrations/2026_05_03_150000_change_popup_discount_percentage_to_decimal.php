<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)->nullable()->change();
        });

        Schema::table('dashed__popup_variants', function (Blueprint $table) {
            $table->decimal('discount_percentage_override', 5, 2)->unsigned()->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->integer('discount_percentage')->nullable()->change();
        });

        Schema::table('dashed__popup_variants', function (Blueprint $table) {
            $table->unsignedInteger('discount_percentage_override')->nullable()->change();
        });
    }
};
