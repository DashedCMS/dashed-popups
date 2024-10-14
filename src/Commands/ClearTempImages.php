<?php

namespace Dashed\DashedFiles\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearTempImages extends Command
{
    public $signature = 'dashed:clear-temp-images';

    public $description = 'Clear temp images';

    public function handle(): int
    {
        $directory = 'storage/media-library/temp';

        if (File::exists($directory)) {
            File::cleanDirectory($directory);
            $this->info('Temp images cleared');
        } else {
            $this->error('Failed to clear temp images');
        }

        return self::SUCCESS;
    }
}
