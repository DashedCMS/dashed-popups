<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->string('type')->default('simple')->after('name');
            $table->boolean('active')->default(false)->after('type');
            $table->text('title')->nullable()->after('active');
            $table->json('blocks')->nullable()->after('title');

            $table->integer('discount_percentage')->nullable()->after('blocks');
            $table->integer('discount_valid_days')->default(14)->after('discount_percentage');
            $table->boolean('auto_apply_discount')->default(true)->after('discount_valid_days');

            $table->string('trigger_type')->default('delay')->after('auto_apply_discount');
            $table->integer('trigger_value')->default(5)->after('trigger_type');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'active', 'title', 'blocks',
                'discount_percentage', 'discount_valid_days', 'auto_apply_discount',
                'trigger_type', 'trigger_value',
            ]);
        });
    }
};
