<?php

namespace Dashed\DashedFiles\Observers;

use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use RalphJSmit\Filament\MediaLibrary\Media\Models\MediaLibraryItem;

class MediaObserver
{
    public function updated(Media $media)
    {
        $filamentMedia = MediaLibraryItem::find($media->model_id);
        foreach (json_decode($filamentMedia->conversions ?: '{}', true) as $conversion) {
            Cache::forget('media-library-media-' . $filamentMedia->id . '-' . mediaHelper()->getConversionName($conversion));
        }
    }
}
