<?php

namespace Dashed\DashedPopups;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedPopups\Filament\Resources\PopupResource;

class DashedPopupsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-popups';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                PopupResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
