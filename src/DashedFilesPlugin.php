<?php

namespace Dashed\DashedFiles;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedFiles\Filament\Pages\FilesPage;

class DashedFilesPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-files';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
//                FilesPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
