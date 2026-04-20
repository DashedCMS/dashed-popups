<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__popup_stats_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('popup_id')->constrained('dashed__popups')->cascadeOnDelete();
            $table->date('date');
            $table->string('device_type', 10)->nullable();
            $table->string('triggered_by', 20)->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('submits')->default(0);
            $table->unsignedInteger('dismissals')->default(0);
            $table->unsignedInteger('bounces')->default(0);
            $table->unsignedBigInteger('sum_time_to_close_ms')->default(0);
            $table->unsignedBigInteger('sum_time_to_submit_ms')->default(0);
            $table->timestamps();
            $table->unique(['popup_id', 'date', 'device_type', 'triggered_by'], 'popup_stats_daily_unique');
            $table->index(['popup_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__popup_stats_daily');
    }
};
