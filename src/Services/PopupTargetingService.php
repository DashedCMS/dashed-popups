<?php

namespace Dashed\DashedPopups\Services;

use Dashed\DashedPopups\Models\Popup;
use Illuminate\Http\Request;

class PopupTargetingService
{
    public function shouldShow(Popup $popup, Request $request): bool
    {
        return true;
    }
}
