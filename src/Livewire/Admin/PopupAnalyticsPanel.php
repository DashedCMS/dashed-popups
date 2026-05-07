<?php

namespace Dashed\DashedPopups\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Computed;
use Dashed\DashedPopups\Models\Popup;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedPopups\Analytics\AiAnalyst;
use Dashed\DashedPopups\Analytics\MetricsResolver;
use Dashed\DashedPopups\Analytics\StatusClassifier;

#[Lazy]
class PopupAnalyticsPanel extends Component
{
    public Popup $popup;

    public string $period = '30';

    public ?string $aiError = null;

    public function mount(Popup $popup): void
    {
        $this->popup = $popup;
    }

    public function updatedPeriod(): void
    {
        // Touching state is enough - computed props recompute automatically on re-render.
    }

    private function cacheKey(string $suffix): string
    {
        return "popup-analytics:{$this->popup->id}:{$this->period}:{$suffix}";
    }

    /**
     * Cache TTL for the analytics-panel computed properties. The component
     * polls every 30s so we only want one underlying query per N polls.
     */
    private const CACHE_TTL = 300;

    #[Computed]
    public function metrics(): array
    {
        return Cache::remember(
            $this->cacheKey('metrics'),
            self::CACHE_TTL,
            fn () => app(MetricsResolver::class)->forPopup(
                $this->popup->id,
                now()->subDays((int) $this->period - 1)->startOfDay(),
                now()->endOfDay(),
            ),
        );
    }

    #[Computed]
    public function breakdownByUrl(): \Illuminate\Support\Collection
    {
        return Cache::remember(
            $this->cacheKey('breakdown-url'),
            self::CACHE_TTL,
            fn () => app(MetricsResolver::class)->breakdownBy(
                $this->popup->id,
                'url',
                now()->subDays((int) $this->period - 1)->startOfDay(),
                now()->endOfDay(),
            ),
        );
    }

    #[Computed]
    public function breakdownByDevice(): \Illuminate\Support\Collection
    {
        return Cache::remember(
            $this->cacheKey('breakdown-device'),
            self::CACHE_TTL,
            fn () => app(MetricsResolver::class)->breakdownBy(
                $this->popup->id,
                'device_type',
                now()->subDays((int) $this->period - 1)->startOfDay(),
                now()->endOfDay(),
            ),
        );
    }

    #[Computed]
    public function breakdownByLocale(): \Illuminate\Support\Collection
    {
        return Cache::remember(
            $this->cacheKey('breakdown-locale'),
            self::CACHE_TTL,
            fn () => app(MetricsResolver::class)->breakdownBy(
                $this->popup->id,
                'locale',
                now()->subDays((int) $this->period - 1)->startOfDay(),
                now()->endOfDay(),
            ),
        );
    }

    #[Computed]
    public function breakdownByReferrer(): \Illuminate\Support\Collection
    {
        return Cache::remember(
            $this->cacheKey('breakdown-referrer'),
            self::CACHE_TTL,
            fn () => app(MetricsResolver::class)->breakdownBy(
                $this->popup->id,
                'referrer_domain',
                now()->subDays((int) $this->period - 1)->startOfDay(),
                now()->endOfDay(),
            ),
        );
    }

    #[Computed]
    public function status(): array
    {
        return app(StatusClassifier::class)->classify($this->metrics());
    }

    #[Computed]
    public function aiAvailable(): bool
    {
        return app(AiAnalyst::class)->isAvailable();
    }

    public function requestAiAnalysis(): void
    {
        $this->aiError = null;
        $result = app(AiAnalyst::class)->analyse($this->popup, $this->metrics(), $this->status());
        if ($result === null) {
            $this->aiError = 'Analyse mislukt. Probeer het later opnieuw.';
        }
        $this->popup->refresh();
    }

    public function render()
    {
        return view('dashed-popups::filament.popups.analytics-tab');
    }
}
