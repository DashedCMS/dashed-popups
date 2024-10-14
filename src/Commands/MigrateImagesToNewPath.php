<?php

namespace Dashed\DashedFiles\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use RalphJSmit\Filament\MediaLibrary\Media\Models\MediaLibraryItem;

class MigrateImagesToNewPath extends Command
{
    public $signature = 'dashed:migrate-images-to-new-path';

    public $description = 'Migrate images to new path';

    public function getOldPath(Media $media): string
    {
        $path = '/';

        if ($media->model->folder ?? false) {
            foreach ($media->model->folder->getAncestors() as $ancestor) {
                $path .= $ancestor->name . '/';
            }
        }

        return $path . basename($media->name) . '/';
    }

    public function handle(): int
    {
        $this->withProgressBar(MediaLibraryItem::all(), function ($item) {
            $mediaItem = $item->getItem();
            $oldPath = trim(rtrim($this->getOldPath($mediaItem), '/'), '/');
            $oldPathFile = $oldPath . '/' . $mediaItem->file_name;
            $newPath = trim(rtrim($mediaItem->getPath(), '/'), '/');
            if (Storage::disk('dashed')->exists($oldPathFile)) {
                Storage::disk('dashed')->move($oldPathFile, $newPath);
                Artisan::call('media-library:regenerate', ['--ids' => $mediaItem->id]);
                Storage::disk('dashed')->deleteDirectory($oldPath);
                $this->info("Moved " . $mediaItem->name);
            } elseif (Storage::disk('dashed')->exists($newPath)) {
                $this->warn("File already moved: " . $oldPathFile);
            } else {
                $this->error("File not found at " . $oldPathFile);
            }
        });

        return self::SUCCESS;
    }
}
