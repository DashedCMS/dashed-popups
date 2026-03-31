<?php

namespace Dashed\DashedPopups;

use Livewire\Livewire;
use Illuminate\Support\Facades\Gate;
use Dashed\DashedPopups\Livewire\Popup;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\PackageServiceProvider;
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

        Gate::policy(\Dashed\DashedPopups\Models\Popup::class, \Dashed\DashedPopups\Policies\PopupPolicy::class);

        cms()->registerRolePermissions('Popups', [
            'view_popup' => 'Popups bekijken',
            'edit_popup' => 'Popups bewerken',
            'delete_popup' => 'Popups verwijderen',
        ]);
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../resources/templates' => resource_path('views/' . config('dashed-core.site_theme', 'dashed')),
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
            ->name('dashed-popups');


        cms()->builder('plugins', [
            new DashedPopupsPlugin(),
        ]);
    }
}
