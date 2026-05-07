<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voegt twee composite-indexes toe op `dashed__popup_views` om de
 * statistiek-kolommen op de PopupResource-overzichtspagina snel te
 * houden bij grotere hoeveelheden views:
 *
 *  - (popup_id, follow_up_started_at, follow_up_cancelled_at)
 *      voor de "In flow"-kolom (`whereNotNull(follow_up_started_at)
 *      ->whereNull(follow_up_cancelled_at)`).
 *  - (popup_id, closed_at)
 *      voor de "Wegklik"-kolom + bounce-rate berekeningen
 *      (`whereNotNull(closed_at)->whereNull(submitted_at)`).
 *
 * `ALGORITHM=INPLACE LOCK=NONE` zodat het toevoegen op een grote tabel
 * geen schrijflock nodig heeft.
 */
return new class () extends Migration {
    private string $table = 'dashed__popup_views';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        $existing = $this->existingIndexes();

        if (
            Schema::hasColumn($this->table, 'follow_up_started_at')
            && Schema::hasColumn($this->table, 'follow_up_cancelled_at')
            && ! in_array('popup_views_pid_followup_idx', $existing, true)
        ) {
            DB::statement(
                "CREATE INDEX popup_views_pid_followup_idx ON {$this->table} (popup_id, follow_up_started_at, follow_up_cancelled_at) ALGORITHM=INPLACE LOCK=NONE"
            );
        }

        if (
            Schema::hasColumn($this->table, 'closed_at')
            && ! in_array('popup_views_pid_closed_idx', $existing, true)
        ) {
            DB::statement(
                "CREATE INDEX popup_views_pid_closed_idx ON {$this->table} (popup_id, closed_at) ALGORITHM=INPLACE LOCK=NONE"
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table)) {
            return;
        }

        $existing = $this->existingIndexes();

        if (in_array('popup_views_pid_followup_idx', $existing, true)) {
            DB::statement("DROP INDEX popup_views_pid_followup_idx ON {$this->table}");
        }

        if (in_array('popup_views_pid_closed_idx', $existing, true)) {
            DB::statement("DROP INDEX popup_views_pid_closed_idx ON {$this->table}");
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
