<?php

namespace Dashed\DashedPopups;

use Dashed\DashedPopups\Commands\RollupPopupStatsCommand;
use Dashed\DashedPopups\Filament\Resources\PopupResource;
use Dashed\DashedPopups\Livewire\Admin\PopupAnalyticsPanel;
use Dashed\DashedPopups\Livewire\Popup;
use Dashed\DashedPopups\Policies\PopupPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DashedPopupsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-popups';

    public function bootingPackage()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'dashed-popups');

        Livewire::component('dashed-popups.popup', Popup::class);
        Livewire::component('dashed-popups.admin.popup-analytics-panel', PopupAnalyticsPanel::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RollupPopupStatsCommand::class,
            ]);
        }

        $this->app->booted(function () {
            /** @var Schedule $schedule */
            $schedule = app(Schedule::class);
            $schedule->command('popups:rollup-stats')->dailyAt('02:00');
        });

        //        $this->app->booted(function () {
        //            $schedule = app(Schedule::class);
        //        });

        cms()->builder('plugins', [
            new DashedPopupsPlugin,
        ]);

        Gate::policy(Models\Popup::class, PopupPolicy::class);

        cms()->registerRolePermissions('Popups', [
            'view_popup' => 'Popups bekijken',
            'edit_popup' => 'Popups bewerken',
            'delete_popup' => 'Popups verwijderen',
        ]);

        cms()->registerResourceDocs(
            resource: PopupResource::class,
            title: 'Popups',
            intro: 'Met popups laat je een boodschap in beeld verschijnen bij bezoekers van de website, bijvoorbeeld voor een actie, nieuwsbrief inschrijving of belangrijke mededeling. Je bepaalt zelf wanneer een popup verschijnt en hoe vaak bezoekers hem te zien krijgen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<'MARKDOWN'
- Een nieuwe popup aanmaken met een eigen titel en inhoud.
- Bestaande popups bewerken of tijdelijk uitschakelen.
- Per popup instellen wanneer hij start en wanneer hij weer stopt.
- De weergavefrequentie per bezoeker regelen.
MARKDOWN,
                ],
                [
                    'heading' => 'Timing van een popup',
                    'body' => 'Een popup die meteen in beeld knalt is irritant, dus je stelt zelf in hoe lang hij wacht voor hij de eerste keer verschijnt. Daarnaast geef je een interval op dat bepaalt hoeveel tijd er tussen twee weergaven bij dezelfde bezoeker moet zitten.',
                ],
                [
                    'heading' => 'Automatische publicatie',
                    'body' => 'Voor een actie die alleen in een bepaalde periode mag lopen kun je een start- en einddatum opgeven. Voor de startdatum is de popup nog niet te zien en na de einddatum verdwijnt hij automatisch weer.',
                ],
            ],
            tips: [
                'Wacht minimaal een paar seconden voordat een popup voor het eerst verschijnt.',
                'Gebruik een duidelijke knop zodat bezoekers weten wat er van hen verwacht wordt.',
                'Zet een ruim interval in zodat terugkerende bezoekers de popup niet te vaak zien.',
                'Plan actiepopups vooraf met een start- en einddatum zodat ze vanzelf lopen.',
            ],
        );
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->mergeConfigFrom(__DIR__.'/../config/popups.php', 'popups');

        $this->publishes([
            __DIR__.'/../config/popups.php' => config_path('popups.php'),
        ], 'dashed-popups-config');

        $this->publishes([
            __DIR__.'/../resources/templates' => resource_path('views/'.config('dashed-core.site_theme', 'dashed')),
        ], 'dashed-templates');

        $package->name('dashed-popups');

        cms()->builder('plugins', [
            new DashedPopupsPlugin,
        ]);
    }
}
