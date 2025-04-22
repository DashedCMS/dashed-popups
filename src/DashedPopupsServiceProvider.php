<?php

namespace Dashed\DashedPopups;

use Dashed\DashedCore\DashedCorePlugin;
use Livewire\Livewire;
use Dashed\DashedPopups\Livewire\Popup;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedPopups\Commands\SendWebhooksForPopupInputs;
use Dashed\DashedPopups\Filament\Pages\Settings\PopupSettingsPage;

class DashedPopupsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-popups';

    public function bootingPackage()
    {
        Livewire::component('dashed-popups.popup', Popup::class);

//        $this->app->booted(function () {
//            $schedule = app(Schedule::class);
//        });

        cms()->builder('plugins', [
            new DashedPopupsPlugin(),
        ]);
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../resources/templates' => resource_path('views/' . env('SITE_THEME', 'dashed')),
        ], 'dashed-templates');

//        cms()->builder(
//            'settingPages',
//            array_merge(cms()->builder('settingPages'), [
//                'popupNotifications' => [
//                    'name' => 'Popupulier instellingen',
//                    'description' => 'Beheer instellingen voor de popupulieren',
//                    'icon' => 'bell',
//                    'page' => PopupSettingsPage::class,
//                ],
//            ])
//        );

        $package
            ->name('dashed-popups')
            ->hasViews();


        cms()->builder('plugins', [
            new DashedPopupsPlugin(),
        ]);
    }
}
