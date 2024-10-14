<?php

namespace Dashed\DashedPopups\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedPopups\DashedPopups
 */
class DashedPopups extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-popups';
    }
}
