<?php

namespace Dashed\DashedPopups\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class PopupPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Popup';
    }
}
