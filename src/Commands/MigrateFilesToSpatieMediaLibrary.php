<?php

namespace Dashed\DashedFiles\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use RalphJSmit\Filament\MediaLibrary\Media\Models\MediaLibraryItem;
use RalphJSmit\Filament\MediaLibrary\Media\Models\MediaLibraryFolder;

class MigrateFilesToSpatieMediaLibrary extends Command
{
    public $signature = 'dashed:migrate-files-to-spatie-media-library';

    public $description = 'Migrate files from dashed to spatie media library';

    public $mediaLibraryItems;

    private function getAllDirectories($disk, $directory = '')
    {
        $directories = Storage::disk($disk)->directories($directory);
        $this->info('Check directory: ' . $directory);
        foreach ($directories as $dir) {
            $directories = array_merge($directories, $this->getAllDirectories($disk, $dir));
        }

        foreach ($directories as $key => $directory) {
            if (str($directory)->contains(['dashed/invoices', 'dashed/packing-slips', 'dashed/keendelivery/labels'])) {
                unset($directories[$key]);
            }
        }

        return $directories;
    }

    public function handle(): int
    {
        //                MediaLibraryFolder::all()->each(fn($folder) => $folder->delete());
        //                MediaLibraryItem::all()->each(fn($item) => $item->delete());
        //                Media::all()->each(fn($media) => $media->delete());

        $this->mediaLibraryItems = MediaLibraryItem::all()->map(function ($item) {
            $item['file_name_to_match'] = basename($item->getItem()->getPath() ?? '');

            return $item;
        });

        //        $folders = Storage::disk('dashed')->allDirectories('dashed');
        $folders = $this->getAllDirectories('dashed', 'dashed');

        $allFolders = [];
        $user = User::first();

        foreach ($folders as &$folder) {
            if (! str($folder)->contains('__media-cache')) {
                $this->info('Migration started for folder: ' . $folder);

                $folder = str($folder)->replace('dashed/', '');
                $parentId = $this->getParentId($folder);

                $newFolder = new MediaLibraryFolder();
                $newFolder->name = $folder;
                $newFolder->parent_id = $parentId;
                if ($otherFolder = MediaLibraryFolder::where('name', str($folder)->explode('/')->last())->where('parent_id', $parentId)->first()) {
                    $newFolder = $otherFolder;
                }
                $newFolder->save();
                $allFolders[] = [
                    'newFolderId' => $newFolder->id,
                    'folder' => $folder,
                ];
                $this->info('Folder created: ' . $folder);
            }
        }

        foreach (MediaLibraryFolder::all() as $folder) {
            $folder->name = str($folder->name)->explode('/')->last();
            $folder->save();
        }

        $folderCount = 1;
        foreach ($allFolders as $folder) {
            $this->info('Migrating files for folder ' . $folderCount . '/' . count($allFolders) . ' ' . $folder['folder']);
            $this->withProgressBar(Storage::disk('dashed')->files('dashed/' . $folder['folder']), function ($file) use ($user, $folder, $allFolders, $folderCount) {
                try {
                    $fileName = basename($file);
                    if (str($fileName)->length() > 200) {
                        $newFileName = str(str($fileName)->explode('/')->last())->substr(50);
                    }

                    if (! $this->mediaLibraryItems->where('file_name_to_match', basename($file))->first() && ! $this->mediaLibraryItems->where('file_name_to_match', basename($newFileName ?? 'not-known'))->first()) {
                        $filamentMediaLibraryItem = new MediaLibraryItem();
                        $filamentMediaLibraryItem->uploaded_by_user_id = $user->id;
                        $filamentMediaLibraryItem->folder_id = $folder['newFolderId'];
                        $filamentMediaLibraryItem->save();

                        $fileName = basename($file);
                        if (str($fileName)->length() > 200) {
                            $newFileName = str(str($fileName)->explode('/')->last())->substr(50);
                            $newFile = str($file)->replace($fileName, $newFileName);
                            Storage::disk('dashed')->copy($file, $newFile);
                            $file = $newFile;
                        }

                        try {
                            $filamentMediaLibraryItem
                                ->addMediaFromDisk($file, 'dashed')
                                ->preservingOriginal()
                                ->toMediaCollection($filamentMediaLibraryItem->getMediaLibraryCollectionName());
                        } catch (\Exception $e) {
                            $this->error('migration failed for file: ' . $file);
                            $this->error($e->getMessage());
                            $filamentMediaLibraryItem->delete();
                        }
                        $this->info('File from folder ' . $folderCount . '/' . count($allFolders) . ' migrated: ' . $file);
                    }
                } catch (\Exception $e) {
                    $this->error('Error migrating file: ' . $file);
                    $this->error($e->getMessage());
                }
            });
            $folderCount++;
        }

        return self::SUCCESS;
    }

    private function getParentId($folder): ?int
    {
        $folders = str($folder)->explode('/')->toArray();
        array_pop($folders);

        return MediaLibraryFolder::where('name', implode('/', $folders))->first()->id ?? null;
    }
}
