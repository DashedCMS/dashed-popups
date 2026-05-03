@php
    $metrics = $this->metrics();
    $status = $this->status();
    $ai = $this->popup->ai_analysis;
    $aiAt = $this->popup->ai_analyzed_at;

    $levelBadge = [
        'good' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
        'warn' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
        'poor' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
        'insufficient_data' => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10',
    ];

    $overallBadge = [
        'excellent' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30',
        'ok' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30',
        'mediocre' => 'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/30',
        'poor' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30',
        'insufficient_data' => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10',
    ];

    $overallLabel = [
        'excellent' => 'Goed',
        'ok' => 'Voldoende',
        'mediocre' => 'Matig',
        'poor' => 'Slecht',
        'insufficient_data' => 'Weinig data',
    ];

    $pct = fn ($v) => number_format(((float) $v) * 100, 1) . '%';
    $num = fn ($v) => number_format((int) $v, 0, ',', '.');

    $card = 'rounded-xl bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm';
    $headingClass = 'text-sm font-semibold text-gray-950 dark:text-white';
    $labelClass = 'text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $metricClass = 'text-2xl font-semibold tracking-tight text-gray-950 dark:text-white';
@endphp

<div class="space-y-6" wire:poll.30s>
    {{-- Header: period selector + overall status --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <label for="popup-analytics-period" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Periode
            </label>
            <select
                id="popup-analytics-period"
                wire:model.live="period"
                class="block rounded-lg border-0 bg-white py-1.5 pl-3 pr-10 text-sm text-gray-950 ring-1 ring-inset ring-gray-950/10 transition focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-500"
            >
                <option value="1">Vandaag</option>
                <option value="7">Laatste 7 dagen</option>
                <option value="30">Laatste 30 dagen</option>
                <option value="90">Laatste 90 dagen</option>
            </select>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $overallBadge[$status['overall']] ?? $overallBadge['insufficient_data'] }}">
            <span class="size-1.5 rounded-full bg-current"></span>
            {{ $overallLabel[$status['overall']] ?? $status['overall'] }}
        </span>
    </div>

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ([
            ['Impressies', $metrics['views'], null, null, null, null],
            ['Submits', $metrics['submits'], 'conversion_rate', $pct($metrics['conversion_rate']), 'conversie', 'heroicon-m-check-circle'],
            ['Wegklik', $metrics['dismissals'], 'dismissal_rate', $pct($metrics['dismissal_rate']), 'wegklik-rate', 'heroicon-m-x-circle'],
            ['Bounces (<2s)', $metrics['bounces'], 'bounce_rate', $pct($metrics['bounce_rate']), 'bounce-rate', 'heroicon-m-arrow-uturn-left'],
        ] as [$label, $value, $mKey, $ratio, $ratioLabel, $icon])
            <div class="{{ $card }} p-4">
                <div class="{{ $labelClass }}">{{ $label }}</div>
                <div class="mt-2 {{ $metricClass }}">{{ $num($value) }}</div>

                @if ($mKey && isset($status['per_metric'][$mKey]))
                    <div class="mt-3 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $levelBadge[$status['per_metric'][$mKey]['level']] ?? '' }}">
                            {{ $ratio }}
                        </span>
                        <span
                            class="text-xs text-gray-500 dark:text-gray-400"
                            title="{{ $status['per_metric'][$mKey]['explanation'] }}"
                        >
                            {{ $ratioLabel }}
                        </span>
                    </div>
                @else
                    <div class="mt-3 h-[1.375rem]"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Breakdown: device + trigger --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ([
            ['Per device', $metrics['by_device'], 'Device'],
            ['Per trigger', $metrics['by_trigger'], 'Trigger'],
        ] as [$title, $rows, $colLabel])
            <div class="{{ $card }} p-5">
                <h3 class="{{ $headingClass }}">{{ $title }}</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left {{ $labelClass }}">
                                <th class="pb-3 font-medium">{{ $colLabel }}</th>
                                <th class="pb-3 text-right font-medium">Impressies</th>
                                <th class="pb-3 text-right font-medium">Submits</th>
                                <th class="pb-3 text-right font-medium">Conversie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="py-2 text-gray-950 dark:text-white">{{ $row['key'] }}</td>
                                    <td class="py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $num($row['views']) }}</td>
                                    <td class="py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $num($row['submits']) }}</td>
                                    <td class="py-2 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $pct($row['conversion_rate']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Geen data
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Top URLs + referrers --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach ([
            ['Top URLs', $metrics['top_urls']],
            ['Top referrers', $metrics['top_referrers']],
        ] as [$title, $rows])
            <div class="{{ $card }} p-5">
                <h3 class="{{ $headingClass }}">{{ $title }}</h3>
                <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($rows as $r)
                        <li class="flex items-center justify-between gap-4 py-2 text-sm" title="{{ $r['value'] }}">
                            <span class="truncate text-gray-950 dark:text-white">
                                {{ \Illuminate\Support\Str::limit($r['value'], 40) ?: '-' }}
                            </span>
                            <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $num($r['views']) }} · {{ $pct($r['conversion_rate']) }}
                            </span>
                        </li>
                    @empty
                        <li class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Geen data</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>

    {{-- ROI --}}
    <div class="{{ $card }} p-5">
        <div class="flex items-center justify-between">
            <h3 class="{{ $headingClass }}">ROI</h3>
            @if ($metrics['submits'] > 0)
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ number_format($metrics['redemption_rate'] * 100, 1) }}% van submits verzilverd
                </span>
            @endif
        </div>
        <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <div class="{{ $labelClass }}">Verzilverd</div>
                <div class="mt-2 {{ $metricClass }}">{{ $num($metrics['redemptions']) }}</div>
            </div>
            <div>
                <div class="{{ $labelClass }}">Omzet</div>
                <div class="mt-2 {{ $metricClass }}">
                    {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($metrics['revenue']) }}
                </div>
            </div>
            <div>
                <div class="{{ $labelClass }}">Korting</div>
                <div class="mt-2 {{ $metricClass }}">
                    {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($metrics['discount_value']) }}
                </div>
            </div>
            <div>
                <div class="{{ $labelClass }}">Netto</div>
                <div class="mt-2 text-2xl font-semibold tracking-tight {{ $metrics['net_revenue'] > 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-950 dark:text-white' }}">
                    {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($metrics['net_revenue']) }}
                </div>
            </div>
        </div>
    </div>

    {{-- AI analysis --}}
    <div class="{{ $card }} p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="{{ $headingClass }}">AI-analyse</h3>
                @if ($aiAt)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Geanalyseerd op {{ optional($aiAt)->format('d-m-Y H:i') }}{{ isset($ai['provider']) ? ' door ' . $ai['provider'] : '' }}
                    </p>
                @endif
            </div>

            @if ($this->aiAvailable())
                <button
                    type="button"
                    wire:click="requestAiAnalysis"
                    wire:loading.attr="disabled"
                    wire:target="requestAiAnalysis"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-600 focus:ring-offset-2 disabled:opacity-60 dark:focus:ring-offset-gray-900"
                >
                    <svg wire:loading wire:target="requestAiAnalysis" class="size-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    {{ $ai ? 'Ververs analyse' : 'Vraag AI om analyse' }}
                </button>
            @else
                <span class="text-xs text-gray-500 dark:text-gray-400">Geen AI-provider geconfigureerd</span>
            @endif
        </div>

        @if ($aiError)
            <div class="mt-4 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                {{ $aiError }}
            </div>
        @endif

        @if ($ai)
            @if (! empty($ai['overall_verdict']))
                <p class="mt-4 text-sm leading-6 text-gray-700 dark:text-gray-300">
                    {{ $ai['overall_verdict'] }}
                </p>
            @endif

            @if (! empty($ai['strengths']))
                <div class="mt-5">
                    <div class="{{ $labelClass }}">Sterke punten</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($ai['strengths'] as $s)
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $levelBadge['good'] }}">
                                {{ $s }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! empty($ai['concerns']))
                <div class="mt-4">
                    <div class="{{ $labelClass }}">Zorgen</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($ai['concerns'] as $c)
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $levelBadge['warn'] }}">
                                {{ $c }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! empty($ai['recommendations']))
                <div class="mt-4">
                    <div class="{{ $labelClass }}">Aanbevelingen</div>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                        @foreach ($ai['recommendations'] as $r)
                            <li>{{ $r }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif
        @elseif (! $aiError)
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Nog geen AI-analyse beschikbaar voor deze popup.
            </p>
        @endif
    </div>

    {{-- Breakdown tabellen per dimensie --}}
    @php
        $dimensions = [
            ['label' => 'Per URL', 'rows' => $this->breakdownByUrl, 'key_label' => 'URL'],
            ['label' => 'Per device', 'rows' => $this->breakdownByDevice, 'key_label' => 'Device'],
            ['label' => 'Per taal', 'rows' => $this->breakdownByLocale, 'key_label' => 'Locale'],
            ['label' => 'Per referrer', 'rows' => $this->breakdownByReferrer, 'key_label' => 'Referrer'],
        ];
        $isDiscount = $this->popup->type === 'discount';
    @endphp

    <section class="space-y-4 pt-6 border-t border-gray-950/5 dark:border-white/10">
        <header>
            <h3 class="{{ $headingClass }}">Waar komt de activiteit vandaan?</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($isDiscount)
                    Views, submits en toegeschreven omzet per bron.
                @else
                    Views en submits per bron. Omzet-attributie werkt alleen voor korting-popups.
                @endif
            </p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($dimensions as $dim)
                <div class="{{ $card }} p-4">
                    <h4 class="{{ $labelClass }}">{{ $dim['label'] }}</h4>
                    @if ($dim['rows']->isEmpty())
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Nog geen data voor deze periode.</p>
                    @else
                        <table class="mt-3 w-full text-sm">
                            <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="pb-2 pr-2">{{ $dim['key_label'] }}</th>
                                    <th class="pb-2 pr-2 text-right">Views</th>
                                    <th class="pb-2 pr-2 text-right">Submits</th>
                                    @if ($isDiscount)
                                        <th class="pb-2 pr-2 text-right">Conv.</th>
                                        <th class="pb-2 text-right">Omzet</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                                @foreach ($dim['rows']->take(10) as $row)
                                    <tr>
                                        <td class="py-1.5 pr-2 text-gray-950 dark:text-white truncate max-w-[14rem]" title="{{ $row->key }}">{{ $row->key }}</td>
                                        <td class="py-1.5 pr-2 text-right text-gray-700 dark:text-gray-300">{{ $num($row->views) }}</td>
                                        <td class="py-1.5 pr-2 text-right text-gray-700 dark:text-gray-300">{{ $num($row->submits) }}</td>
                                        @if ($isDiscount)
                                            <td class="py-1.5 pr-2 text-right text-gray-700 dark:text-gray-300">{{ $num($row->redemptions) }}</td>
                                            <td class="py-1.5 text-right text-gray-950 dark:text-white font-medium">&euro; {{ number_format($row->revenue, 2, ',', '.') }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</div>