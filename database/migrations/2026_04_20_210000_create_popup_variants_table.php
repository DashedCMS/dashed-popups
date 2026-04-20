<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__popup_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('popup_id')->constrained('dashed__popups')->cascadeOnDelete();
            $table->string('name');
            $table->string('code_prefix', 20);
            $table->unsignedInteger('discount_percentage_override')->nullable();
            $table->unsignedInteger('discount_valid_days_override')->nullable();
            $table->unsignedInteger('split_weight')->default(50);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['popup_id', 'enabled']);
            $table->unique(['popup_id', 'code_prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__popup_variants');
    }
};
