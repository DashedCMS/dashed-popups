<?php

namespace Dashed\DashedPopups\Analytics;

use Dashed\DashedAi\AiManager;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedAi\Enums\AiCapability;

class AiAnalyst
{
    public function __construct(private readonly AiManager $ai)
    {
    }

    public function isAvailable(): bool
    {
        return $this->ai->default(AiCapability::Json) !== null;
    }

    /**
     * @return ?array{overall_verdict:string, strengths:array, concerns:array, recommendations:array}
     */
    public function analyse(Popup $popup, array $metrics, array $status): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $prompt = $this->prompt($popup, $metrics, $status);
        $result = $this->ai->json($prompt);

        if (! is_array($result)) {
            return null;
        }

        $result = [
            'overall_verdict' => (string) ($result['overall_verdict'] ?? ''),
            'strengths' => array_values(array_filter((array) ($result['strengths'] ?? []))),
            'concerns' => array_values(array_filter((array) ($result['concerns'] ?? []))),
            'recommendations' => array_values(array_filter((array) ($result['recommendations'] ?? []))),
            'provider' => $this->ai->default(AiCapability::Json)?->name(),
        ];

        $popup->update([
            'ai_analysis' => $result,
            'ai_analyzed_at' => now(),
        ]);

        return $result;
    }

    private function prompt(Popup $popup, array $metrics, array $status): string
    {
        $conv = number_format(($metrics['conversion_rate'] ?? 0) * 100, 2);
        $bounce = number_format(($metrics['bounce_rate'] ?? 0) * 100, 2);
        $dismiss = number_format(($metrics['dismissal_rate'] ?? 0) * 100, 2);
        $avgSubmit = $metrics['avg_time_to_submit'] ? round($metrics['avg_time_to_submit'] / 1000, 1).'s' : 'n.v.t.';
        $trend = $metrics['trend_7d_vs_30d'] === null ? 'onbekend' : sprintf('%+.0f%%', $metrics['trend_7d_vs_30d'] * 100);
        $topDevice = $metrics['by_device'][0]['key'] ?? 'onbekend';
        $topTrigger = $metrics['by_trigger'][0]['key'] ?? 'onbekend';

        $name = is_array($popup->name) ? ($popup->name[app()->getLocale()] ?? reset($popup->name)) : $popup->name;

        $lines = [];
        foreach ($status['per_metric'] ?? [] as $k => $v) {
            $lines[] = "- {$k}: {$v['level']}";
        }
        $statusBlock = implode("\n", $lines);

        return <<<PROMPT
Je bent een e-commerce optimalisatie-expert. Analyseer onderstaande popup-statistieken
en geef concrete verbeteradviezen. Antwoord in het Nederlands. Wees bondig (max 150 woorden totaal).

Popup: {$name}
Type: {$popup->type}
Trigger-config: {$popup->trigger_type}
Periode: {$metrics['period_from']} t/m {$metrics['period_to']}

Metrics:
- Views: {$metrics['views']}
- Submits: {$metrics['submits']} ({$conv}%)
- Bounces (<2s weg): {$metrics['bounces']} ({$bounce}%)
- Dismissals: {$metrics['dismissals']} ({$dismiss}%)
- Gemiddelde tijd tot submit: {$avgSubmit}
- Trend 7d vs 30d: {$trend}
- Top device: {$topDevice}
- Top trigger: {$topTrigger}

Status-classificatie:
{$statusBlock}

Geef antwoord als JSON met precies deze structuur:
{
  "overall_verdict": "een of twee zinnen samenvatting",
  "strengths": ["punt 1", "punt 2"],
  "concerns": ["punt 1", "punt 2"],
  "recommendations": ["actie 1", "actie 2", "actie 3"]
}
PROMPT;
    }
}
