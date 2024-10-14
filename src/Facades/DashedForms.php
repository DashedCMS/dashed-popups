<?php

namespace Dashed\DashedForms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedForms\DashedForms
 */
class DashedForms extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-forms';
    }
}
