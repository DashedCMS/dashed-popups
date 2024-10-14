<?php

namespace Dashed\DashedForms;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Dashed\DashedForms\Filament\Pages\Settings\FormSettingsPage;

class DashedFormsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-forms';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                FormSettingsPage::class,
            ])
            ->resources([
                FormResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
