<?php

namespace Dashed\DashedFiles;

use Spatie\Image\Enums\Fit;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedFiles\Observers\MediaObserver;
use Dashed\DashedFiles\Commands\ClearTempImages;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Dashed\DashedFiles\Commands\MigrateImagesToNewPath;
use Dashed\DashedFiles\Commands\MigrateImagesInDatabase;
use Dashed\DashedFiles\Observers\MediaLibraryitemObserver;
use RalphJSmit\Filament\MediaLibrary\Facades\MediaLibrary;
use Dashed\DashedFiles\Commands\MigrateFilesToSpatieMediaLibrary;
use RalphJSmit\Filament\MediaLibrary\Media\Models\MediaLibraryItem;

class DashedFilesServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-files';

    public function bootingPackage()
    {
        MediaLibrary::registerMediaConversions(function (MediaLibraryItem $mediaLibraryItem, Media $media = null) {
            $mediaLibraryItemConversions = json_decode(MediaLibraryItem::find($media->model_id)->conversions ?: '{}', true);

            foreach ($mediaLibraryItemConversions as $conversion) {
                if (is_array($conversion)) {
                    foreach ($conversion as $key => $value) {
                        if ($key == 'widen') {
                            $mediaLibraryItem
                                ->addMediaConversion(mediaHelper()->getConversionName($conversion))
                                ->format('webp')
                                ->width(is_array($value) ? $value[0] : $value);
                        } elseif ($key == 'heighten') {
                            $mediaLibraryItem
                                ->addMediaConversion(mediaHelper()->getConversionName($conversion))
                                ->format('webp')
                                ->width(is_array($value) ? $value[0] : $value);
                        } elseif ($key == 'fit') {
                            $mediaLibraryItem
                                ->addMediaConversion(mediaHelper()->getConversionName($conversion))
                                ->format('webp')
                                ->fit(Fit::Crop, $value[0], $value[1]);
                        } elseif ($key == 'contain') {
                            $mediaLibraryItem
                                ->addMediaConversion(mediaHelper()->getConversionName($conversion))
                                ->format('webp')
                                ->fit(Fit::Contain, $value[0], $value[1]);
                        }
                    }
                } elseif ($conversion == 'original') {
                    //Do nothing
                } elseif ($conversion == 'huge') {
                    $mediaLibraryItem
                        ->addMediaConversion('huge')
                        ->format('webp')
                        ->width(1600);
                } elseif ($conversion == 'large') {
                    $mediaLibraryItem
                        ->addMediaConversion('large')
                        ->format('webp')
                        ->width(1200);
                } elseif ($conversion == 'small') {
                    $mediaLibraryItem
                        ->addMediaConversion('small')
                        ->format('webp')
                        ->width(400);
                } elseif ($conversion == 'tiny') {
                    $mediaLibraryItem
                        ->addMediaConversion('tiny')
                        ->format('webp')
                        ->width(200);
                }
                $mediaLibraryItem
                    ->addMediaConversion('medium')
                    ->format('webp')
                    ->width(800);
            }
        });

        //        MediaLibraryItem::observe(MediaLibraryItemObserver::class);
    }

    public function packageBooted()
    {
        Media::observe(MediaObserver::class);

        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command('dashed:clear-temp-images')->daily();
        });
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dashed-files');

        $package
            ->name('dashed-files')
            ->hasViews()
            ->hasCommands([
                MigrateFilesToSpatieMediaLibrary::class,
                MigrateImagesInDatabase::class,
                MigrateImagesToNewPath::class,
                ClearTempImages::class,
            ])
            ->hasConfigFile([
                'media-library',
            ]);
    }
}
