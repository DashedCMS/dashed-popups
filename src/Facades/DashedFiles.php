<?php

namespace Dashed\DashedFiles\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedFiles\DashedFiles
 */
class DashedFiles extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-files';
    }
}
