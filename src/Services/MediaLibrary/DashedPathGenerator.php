<?php

namespace Dashed\DashedFiles\Services\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class DashedPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $media->uuid . '/';
        $path = '/';

        if ($media->model->folder ?? false) {
            foreach ($media->model->folder->getAncestors() as $ancestor) {
                $path .= $ancestor->name . '/';
            }
        }

        return $path . basename($media->name) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive/';
    }
}
