<?php

namespace Dashed\DashedFiles\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RalphJSmit\Filament\MediaLibrary\Media\Models\MediaLibraryItem;

class MigrateImagesInDatabase extends Command
{
    public $signature = 'dashed:migrate-images-in-database  {--empty-not-found}';

    public $description = 'Migrate images in database';

    public $mediaLibraryItems;
    public int $failedToMigrateCount = 0;
    public array $failedToMigrate = [];

    private function getTablesToSkip(): array
    {
        return [
            'activity_log',
            'dashed__url_history',
            'dashed__media_files',
            'dashed__media_folders',
            'media',
            'filament_media_library',
            'filament_media_library_folders',
            'migrations',
            'password_resets',
            'users',
            'seo_scores',
            'seo_scans',
            'personal_access_tokens',
            'failed_jobs',
            'jobs',
            'sessions',
            'telescope_entries',
            'telescope_entries_tags',
            'dashed__not_found_pages',
            'dashed__not_found_page_occurrences',
            'dashed__order_logs',
            'dashed__form_inputs',
            'dashed__form_input_fields',
            'dashed__orders',
            'dashed__order_products',
            'dashed__order_payments',
        ];
    }

    private function getColumnsToSkip(): array
    {
        return [
            'id',
            'name',
            'title',
            'slug',
            'created_at',
            'updated_at',
            'deleted_at',
            'ip',
            'like',
            'url',
            'color',
            'site_ids',
            'start_date',
            'end_date',
            'parent_id',
            'user_agent',
            'from_url',
            'locale',
            'viewed',
            'first_name',
            'last_name',
            'phone_number',
            'email',
            'function',
        ];
    }

    public function handle(): int
    {

        $this->mediaLibraryItems = MediaLibraryItem::all()->map(function ($item) {
            $item['file_name_to_match'] = basename($item->getItem()->getPath() ?? '');

            return $item;
        });

        $tables = DB::select('SHOW TABLES');
        $tablesToSkip = $this->getTablesToSkip();
        $columnsToSkip = $this->getColumnsToSkip();
        $databaseName = DB::getDatabaseName();

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_$databaseName"};
            if (! in_array($tableName, $tablesToSkip)) {
                $this->info('Checking table: ' . $tableName);

                // Get all columns of the table
                $columns = Schema::getColumnListing($tableName);

                $this->withProgressBar($columns, function ($column) use ($tableName, $columnsToSkip) {
                    if (! in_array($column, $columnsToSkip) || str($column)->endsWith('_id')) {
                        $this->info('checking column: ' . $column . ' in table: ' . $tableName);
                        DB::table($tableName)->select('id', $column)->orderBy('id')->chunk(100, function ($rows) use ($column, $tableName) {
                            foreach ($rows as $row) {
                                $this->checkValueForImagePath($row->$column, $tableName, $column, $row->id);
                            }
                        });
                    }
                });
            }
        }

        if ($this->failedToMigrateCount > 0) {
            $this->error('Failed to migrate count: ' . $this->failedToMigrateCount);
        } else {
            $this->info('All images migrated successfully');
        }

        $this->table(
            [
                'Tabel',
                'ID',
                'Kolom',
                'Waarde',
            ],
            $this->failedToMigrate
        );

        return self::SUCCESS;
    }

    private function checkValueForImagePath($value, $tableName, $columnName, $rowId)
    {
        if (is_string($value)) {
            if ($this->isJson($value)) {
                $decodedValue = json_decode($value, true);
                $this->checkValueForImagePath($decodedValue, $tableName, $columnName, $rowId);
            } else {
                $this->performAction($tableName, $columnName, $value, $rowId);
            }
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                $this->checkValueForImagePath($item, $tableName, $columnName, $rowId);
            }
        }
    }

    private function isJson($string)
    {
        json_decode($string);

        return (json_last_error() == JSON_ERROR_NONE);
    }

    private function containsDotInLast10Chars($string)
    {
        // Get the last 10 characters of the string
        $lastTenChars = substr($string, -10);

        // Check if the last 10 characters contain a dot
        if (strpos($lastTenChars, '.') !== false) {
            return true;
        } else {
            return false;
        }
    }

    private function performAction($tableName, $columnName, $value, $rowId)
    {
        if ($this->containsDotInLast10Chars($value)) {
            try {
                $fileExists = Storage::disk('dashed')->exists($value);
                if (! str($value)->contains('/')) {
                    $fileExists = false;
                }
            } catch (Exception $exception) {
                $fileExists = false;
            }
        } else {
            $fileExists = false;
        }

        if ($fileExists) {
            $filePassedChecks = true;
            $oldValue = $value;
            $fileToCheck = basename($value);
            if (str($fileToCheck)->length() > 200) {
                //                dump($value);
                //                $value = str(str($fileToCheck)->explode('/')->last())->substr(50);
                $newFileName = str(str($value)->explode('/')->last())->substr(50);
                $value = str($value)->replace($fileToCheck, $newFileName);
                //                dump($value);
            }
            if ($mediaItem = $this->mediaLibraryItems->where('file_name_to_match', basename($value))->first()) {
                try {
                    $filePassedChecks = Storage::disk('dashed')->exists($mediaItem->getItem()->getPath());
                    if (! str($value)->contains('/')) {
                        $filePassedChecks = false;
                    }
                } catch (Exception $exception) {
                    $filePassedChecks = false;
                }
                if ($filePassedChecks) {
                    $currentValue = DB::table($tableName)
                        ->where('id', $rowId)
                        ->select($columnName)
                        ->first();
                    DB::table($tableName)
                        ->where('id', $rowId)
                        ->update([
                            $columnName => str($currentValue->$columnName)->replace($oldValue, $mediaItem->id),
                        ]);
                    $this->info('Replacement made in ' . $tableName . ' for ' . $columnName . ' with id ' . $rowId . ' with value ' . $value . ' with ' . $mediaItem->id);
                }
            } else {
                //                dump('not matched in libraryMediaItems');
                $filePassedChecks = false;
            }

            if (! $filePassedChecks) {
                $emptyNotFound = $this->option('empty-not-found');
                if ($emptyNotFound) {
                    $currentValue = DB::table($tableName)
                        ->where('id', $rowId)
                        ->select($columnName)
                        ->first();
                    DB::table($tableName)
                        ->where('id', $rowId)
                        ->update([
                            $columnName => str($currentValue->$columnName)->replace($value, ''),
                        ]);
                } else {
                    $this->error('Media item not found for ' . $value . ' in ' . $tableName . ' for ' . $columnName . ' with id ' . $rowId);
                    $this->failedToMigrate[] = [
                        $tableName,
                        $rowId,
                        $columnName,
                        $value,
                    ];
                    $this->failedToMigrateCount++;
                }
            }
        }
    }
}
