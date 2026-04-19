<?php

namespace Dashed\DashedPopups\Analytics;

class StatusClassifier
{
    /**
     * @param  array  $metrics  output of MetricsResolver::forPopup()
     * @return array{overall:string, per_metric:array<string,array{level:string,explanation:string}>}
     */
    public function classify(array $metrics): array
    {
        $thresholds = config('popups.analytics.thresholds', []);
        $minViews = (int) ($thresholds['min_views_for_confidence'] ?? 100);

        if (($metrics['views'] ?? 0) < $minViews) {
            return [
                'overall' => 'insufficient_data',
                'per_metric' => [],
            ];
        }

        $perMetric = [
            'conversion_rate' => $this->score($metrics, 'conversion_rate', higherIsBetter: true),
            'bounce_rate' => $this->score($metrics, 'bounce_rate', higherIsBetter: false),
            'dismissal_rate' => $this->score($metrics, 'dismissal_rate', higherIsBetter: false),
        ];

        $levels = array_column($perMetric, 'level');
        $poor = count(array_filter($levels, fn ($l) => $l === 'poor'));
        $good = count(array_filter($levels, fn ($l) => $l === 'good'));

        $overall = match (true) {
            $poor >= 2 => 'poor',
            $poor === 1 => 'mediocre',
            $good >= 2 => 'excellent',
            default => 'ok',
        };

        return ['overall' => $overall, 'per_metric' => $perMetric];
    }

    private function score(array $metrics, string $key, bool $higherIsBetter): array
    {
        $value = (float) ($metrics[$key] ?? 0.0);
        $good = (float) (config("popups.analytics.thresholds.{$key}.good") ?? 0);
        $warn = (float) (config("popups.analytics.thresholds.{$key}.warn") ?? 0);

        if ($higherIsBetter) {
            $level = match (true) {
                $value >= $good => 'good',
                $value >= $warn => 'warn',
                default => 'poor',
            };
        } else {
            $level = match (true) {
                $value <= $good => 'good',
                $value <= $warn => 'warn',
                default => 'poor',
            };
        }

        $pct = round($value * 100, 1);
        $goodPct = round($good * 100, 1);
        $warnPct = round($warn * 100, 1);

        // For higher-is-better metrics, "good" means the value is ABOVE the good threshold.
        // For lower-is-better (bounce/dismissal), "good" means the value is BELOW the good threshold.
        $goodWord = $higherIsBetter ? 'boven' : 'onder';
        $poorWord = $higherIsBetter ? 'onder' : 'boven';
        $goodSym = $higherIsBetter ? '>=' : '<=';
        $poorSym = $higherIsBetter ? '<' : '>';

        $explanation = match ($level) {
            'good' => "{$this->label($key)} is {$pct}% - {$goodWord} drempel ({$goodSym}{$goodPct}%)",
            'warn' => "{$this->label($key)} is {$pct}% - tussen drempels ({$warnPct}% en {$goodPct}%)",
            'poor' => "{$this->label($key)} is {$pct}% - {$poorWord} drempel ({$poorSym}{$warnPct}%)",
        };

        return ['level' => $level, 'explanation' => $explanation];
    }

    private function label(string $key): string
    {
        return match ($key) {
            'conversion_rate' => 'Conversie',
            'bounce_rate' => 'Bounce rate',
            'dismissal_rate' => 'Dismissal rate',
            default => $key,
        };
    }
}
