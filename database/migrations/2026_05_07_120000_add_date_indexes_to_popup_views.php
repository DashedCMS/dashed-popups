<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    private string $table = 'dashed__popup_views';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable($this->table)) {
            return;
        }

        $existing = $this->existingIndexes();

        if (! in_array('popup_views_pid_first_seen_idx', $existing, true)) {
            DB::statement(
                "CREATE INDEX popup_views_pid_first_seen_idx ON {$this->table} (popup_id, first_seen_at) ALGORITHM=INPLACE LOCK=NONE"
            );
        }

        if (! in_array('popup_views_pid_created_at_idx', $existing, true)) {
            DB::statement(
                "CREATE INDEX popup_views_pid_created_at_idx ON {$this->table} (popup_id, created_at) ALGORITHM=INPLACE LOCK=NONE"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable($this->table)) {
            return;
        }

        $existing = $this->existingIndexes();

        if (in_array('popup_views_pid_first_seen_idx', $existing, true)) {
            DB::statement("DROP INDEX popup_views_pid_first_seen_idx ON {$this->table}");
        }

        if (in_array('popup_views_pid_created_at_idx', $existing, true)) {
            DB::statement("DROP INDEX popup_views_pid_created_at_idx ON {$this->table}");
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
