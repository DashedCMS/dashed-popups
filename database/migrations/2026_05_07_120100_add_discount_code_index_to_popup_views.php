<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private string $table = 'dashed__popup_views';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        $existing = $this->existingIndexes();

        // (discount_code_id, popup_id) lets the redemption JOIN
        // (orders -> popup_views by discount_code_id) use an index lookup
        // instead of a full popup_views scan.
        if (! in_array('popup_views_disc_pid_idx', $existing, true)) {
            DB::statement(
                "CREATE INDEX popup_views_disc_pid_idx ON {$this->table} (discount_code_id, popup_id) ALGORITHM=INPLACE LOCK=NONE"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        $existing = $this->existingIndexes();

        if (in_array('popup_views_disc_pid_idx', $existing, true)) {
            DB::statement("DROP INDEX popup_views_disc_pid_idx ON {$this->table}");
        }
    }

    private function existingIndexes(): array
    {
        return collect(DB::select("SHOW INDEXES FROM {$this->table}"))
            ->pluck('Key_name')
            ->unique()
            ->all();
    }
};
