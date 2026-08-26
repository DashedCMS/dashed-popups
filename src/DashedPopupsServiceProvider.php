<?php

namespace Dashed\DashedPopups;

use Livewire\Livewire;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Dashed\DashedPopups\Livewire\Popup;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedCore\Retention\Termijn;
use Dashed\DashedCore\Retention\Retention;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedPopups\Policies\PopupPolicy;
use Dashed\DashedPopups\Analytics\RollupService;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedPopups\Commands\RollupPopupStatsCommand;
use Dashed\DashedPopups\Filament\Resources\PopupResource;
use Dashed\DashedPopups\Filament\Widgets\PopupActiveStat;
use Dashed\DashedPopups\Filament\Widgets\PopupFunnelWidget;
use Dashed\DashedPopups\Livewire\Admin\PopupAnalyticsPanel;
use Dashed\DashedPopups\Commands\RecalculatePopupStatsCommand;
use Dashed\DashedPopups\Commands\BackfillPopupOrderMatchesCommand;
use Dashed\DashedPopups\Filament\Widgets\PopupPerformanceOverview;
use Dashed\DashedPopups\Listeners\CancelPopupFollowUpsOnPaidOrder;

class DashedPopupsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-popups';

    public function bootingPackage()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'dashed-popups');

        Livewire::component('dashed-popups.popup', Popup::class);
        Livewire::component('dashed-popups.admin.popup-analytics-panel', PopupAnalyticsPanel::class);
        Livewire::component('dashed.dashed-popups.filament.widgets.popup-performance-overview', PopupPerformanceOverview::class);
        Livewire::component('dashed.dashed-popups.filament.widgets.popup-active-stat', PopupActiveStat::class);
        Livewire::component('dashed.dashed-popups.filament.widgets.popup-funnel-widget', PopupFunnelWidget::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RollupPopupStatsCommand::class,
                RecalculatePopupStatsCommand::class,
                BackfillPopupOrderMatchesCommand::class,
            ]);
        }

        $this->app->booted(function () {
            /** @var Schedule $schedule */
            $schedule = app(Schedule::class);
            $schedule->command('popups:rollup-stats')->dailyAt('02:00');
            $schedule->command('dashed:recalculate-popup-stats')->hourly()->withoutOverlapping();

            // De eigen nieuwsbrief moet overal te kiezen zijn waar een koppeling
            // als Laposta dat ook is. In booted() en achter een guard: dit
            // pakket vereist de nieuwsbriefmodule niet, en in de register-fase
            // bestaat die binding nog niet.
            if (! app()->bound('newsletter')) {
                return;
            }

            forms()->builder(
                'popupApiClasses',
                array_merge(forms()->builder('popupApiClasses'), [
                    'newsletter-popup-api' => [
                        'name' => 'Nieuwsbrieflijst in het CMS',
                        'class' => \Dashed\DashedPopups\Newsletter\NewsletterPopupAPI::class,
                    ],
                ])
            );
        });

        //        $this->app->booted(function () {
        //            $schedule = app(Schedule::class);
        //        });

        cms()->builder('plugins', [
            new DashedPopupsPlugin(),
        ]);

        cms()->builder('summaryContributors', [\Dashed\DashedPopups\Services\Summary\PopupSummaryContributor::class]);

        if (class_exists(\Dashed\DashedEcommerceCore\Models\Order::class)) {
            \Dashed\DashedEcommerceCore\Models\Order::observe(
                \Dashed\DashedPopups\Observers\OrderPopupMatchObserver::class
            );
        }

        if (class_exists(\Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent::class)) {
            Event::listen(
                \Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent::class,
                CancelPopupFollowUpsOnPaidOrder::class,
            );
        }

        Gate::policy(Models\Popup::class, PopupPolicy::class);

        cms()->registerRolePermissions('Popups', [
            'view_popup' => 'Popups bekijken',
            'edit_popup' => 'Popups bewerken',
            'delete_popup' => 'Popups verwijderen',
        ]);

        cms()->registerSettingsPage(
            \Dashed\DashedPopups\Filament\Pages\Settings\PopupSettingsPage::class,
            'Popups',
            'cursor-arrow-ripple',
            'Instellingen voor popups, zoals de minimale tijd tussen popups.'
        );

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

        self::registreerBewaartermijnen();
    }

    /**
     * De popup-vertoningen aanmelden bij het bewaartermijnenregister.
     *
     * Statisch en apart van bootingPackage(), zodat een test hem opnieuw kan
     * aanroepen na app(RetentionRegistry::class)->flush(). Deze __()-aanroepen
     * mogen niet in registeringPackage() of packageRegistered() staan: die
     * fase draait voordat de vertaalservice klaarstaat en zou de hele boot
     * laten klappen.
     */
    public static function registreerBewaartermijnen(): void
    {
        cms()->registerRetention(
            Retention::make('popup_views')
                ->label(__('Popup-vertoningen'))
                ->pakket('dashed-popups', __('Popups'))
                ->tabel('dashed__popup_views')
                // Zonder deze haak lopen de dagcijfers uit de pas met de rijen
                // die zo verdwijnen: de nachtelijke rollup kijkt maar zeven
                // dagen terug, dus een stilgestane scheduler laat gaten
                // vallen die hierna niet meer in te halen zijn.
                ->vooraf(fn ($grens) => app(RollupService::class)->zorgVoorDekkingTot($grens))
                ->termijn(
                    Termijn::make('popup_views', 90, 'created_at')
                        ->label(__('Popup-vertoningen bewaren (dagen)'))
                        ->uitleg(__('Anonieme vertoningen. Inzendingen, kortingscodes en aan een bestelling gekoppelde vertoningen blijven altijd staan. De dagcijfers blijven bewaard, maar de uitsplitsing per URL, taal en verwijzer werkt alleen binnen deze termijn. Standaard: 90 dagen.'))
                        ->filter(fn ($query) => $query
                            ->whereNull('submitted_at')
                            ->whereNull('email')
                            ->whereNull('user_id')
                            ->whereNull('discount_code_id')
                            ->whereNull('matched_order_id')
                            ->whereNull('follow_up_started_at'))
                )
        );
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/frontend.php');

        $this->mergeConfigFrom(__DIR__.'/../config/popups.php', 'popups');

        $this->publishes([
            __DIR__.'/../config/popups.php' => config_path('popups.php'),
        ], 'dashed-popups-config');

        $this->publishes([
            __DIR__.'/../resources/templates' => resource_path('views/'.config('dashed-core.site_theme', 'dashed')),
        ], 'dashed-templates');

        $package->name('dashed-popups');

        cms()->builder('plugins', [
            new DashedPopupsPlugin(),
        ]);
    }
}
