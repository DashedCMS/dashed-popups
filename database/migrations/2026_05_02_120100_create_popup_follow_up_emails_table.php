<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__popup_follow_up_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')
                ->constrained('dashed__popup_follow_up_flows')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('send_after_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->json('subject')->nullable();
            $table->json('blocks')->nullable();
            $table->timestamps();

            $table->index(['flow_id', 'sort']);
            $table->index(['flow_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__popup_follow_up_emails');
    }
};
