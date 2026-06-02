<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popups', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__popups', 'discount_type')) {
                $table->string('discount_type')->default('percentage')->after('discount_percentage');
            }
            if (! Schema::hasColumn('dashed__popups', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->nullable()->after('discount_type');
            }
            if (! Schema::hasColumn('dashed__popups', 'minimal_requirements')) {
                $table->string('minimal_requirements')->nullable()->after('discount_amount');
            }
            if (! Schema::hasColumn('dashed__popups', 'minimum_amount')) {
                $table->decimal('minimum_amount', 10, 2)->nullable()->after('minimal_requirements');
            }
            if (! Schema::hasColumn('dashed__popups', 'minimum_products_count')) {
                $table->integer('minimum_products_count')->nullable()->after('minimum_amount');
            }
            if (! Schema::hasColumn('dashed__popups', 'valid_for')) {
                $table->string('valid_for')->nullable()->after('minimum_products_count');
            }
        });

        if (! Schema::hasTable('dashed__popup_discount_product')) {
            Schema::create('dashed__popup_discount_product', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('popup_id');
                $table->unsignedBigInteger('product_id');
                $table->index('popup_id', 'pdp_popup_idx');
                $table->unique(['popup_id', 'product_id'], 'pdp_popup_product_unique');
            });
        }

        if (! Schema::hasTable('dashed__popup_discount_category')) {
            Schema::create('dashed__popup_discount_category', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('popup_id');
                $table->unsignedBigInteger('product_category_id');
                $table->index('popup_id', 'pdc_popup_idx');
                $table->unique(['popup_id', 'product_category_id'], 'pdc_popup_category_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__popup_discount_product');
        Schema::dropIfExists('dashed__popup_discount_category');

        Schema::table('dashed__popups', function (Blueprint $table) {
            foreach ([
                'discount_type', 'discount_amount', 'minimal_requirements',
                'minimum_amount', 'minimum_products_count', 'valid_for',
            ] as $column) {
                if (Schema::hasColumn('dashed__popups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
