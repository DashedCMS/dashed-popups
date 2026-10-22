<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__popups') || Schema::hasColumn('dashed__popups', 'newsletter_segment_id')) {
            return;
        }

        Schema::table('dashed__popups', function (Blueprint $table) {
            // Bewust geen foreign key: popups werkt ook zonder het
            // nieuwsbriefpakket, en dan bestaat de segmententabel niet. Een
            // verwijderd segment laat de popup voor niemand meer zien
            // (Popup::matchesAudience()), nooit voor iedereen.
            $table->unsignedBigInteger('newsletter_segment_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__popups', 'newsletter_segment_id')) {
            return;
        }

        Schema::table('dashed__popups', function (Blueprint $table) {
            $table->dropColumn('newsletter_segment_id');
        });
    }
};
