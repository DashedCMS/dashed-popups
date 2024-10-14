<?php


use Dashed\DashedFiles\Classes\MediaHelper;

if (! function_exists('mediaHelper')) {
    function mediaHelper(): MediaHelper
    {
        return app(MediaHelper::class);
    }
}
