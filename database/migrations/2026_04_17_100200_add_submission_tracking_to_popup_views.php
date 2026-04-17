<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('session_id');
            $table->unsignedBigInteger('discount_code_id')->nullable()->after('submitted_at');
            $table->index(['popup_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('dashed__popup_views', function (Blueprint $table) {
            $table->dropIndex(['popup_id', 'submitted_at']);
            $table->dropColumn(['submitted_at', 'discount_code_id']);
        });
    }
};
