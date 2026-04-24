<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__popup_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('popup_id')
                ->constrained('dashed__popups')
                ->cascadeOnDelete();
            $table->enum('rule_type', ['include', 'exclude']);
            $table->enum('match_type', ['url_pattern', 'all_of_type', 'specific_model']);
            $table->string('pattern', 500)->nullable();
            $table->string('targetable_type')->nullable();
            $table->unsignedBigInteger('targetable_id')->nullable();
            $table->timestamps();

            $table->index(['popup_id', 'rule_type', 'match_type'], 'popup_targets_lookup_idx');
            $table->index(['targetable_type', 'targetable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__popup_targets');
    }
};
