<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Concerns;

use Illuminate\Support\Facades\DB;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Models\PopupTarget;

trait SyncsPopupTargets
{
    protected function syncPopupTargets(Popup $popup, array $data): void
    {
        DB::transaction(function () use ($popup, $data) {
            // URL patterns
            foreach (['include', 'exclude'] as $ruleType) {
                $popup->targets()
                    ->where('rule_type', $ruleType)
                    ->where('match_type', 'url_pattern')
                    ->delete();

                $patterns = $data["{$ruleType}_url_patterns"] ?? [];
                foreach ($patterns as $row) {
                    $pattern = is_array($row) ? ($row['pattern'] ?? null) : $row;
                    if ($pattern) {
                        $popup->targets()->create([
                            'rule_type' => $ruleType,
                            'match_type' => 'url_pattern',
                            'pattern' => $pattern,
                        ]);
                    }
                }
            }

            // Model targeting per routeModel
            foreach (cms()->builder('routeModels') ?? [] as $key => $routeModel) {
                $modelClass = $routeModel['class'] ?? null;
                if (! $modelClass) {
                    continue;
                }

                foreach (['include', 'exclude'] as $ruleType) {
                    $popup->targets()
                        ->where('rule_type', $ruleType)
                        ->whereIn('match_type', ['all_of_type', 'specific_model'])
                        ->where('targetable_type', $modelClass)
                        ->delete();

                    $mode = $data["target_mode_{$ruleType}_{$key}"] ?? 'none';

                    if ($mode === 'all') {
                        $popup->targets()->create([
                            'rule_type' => $ruleType,
                            'match_type' => 'all_of_type',
                            'targetable_type' => $modelClass,
                        ]);
                    } elseif ($mode === 'selected') {
                        foreach ((array) ($data["target_ids_{$ruleType}_{$key}"] ?? []) as $id) {
                            if ($id) {
                                $popup->targets()->create([
                                    'rule_type' => $ruleType,
                                    'match_type' => 'specific_model',
                                    'targetable_type' => $modelClass,
                                    'targetable_id' => $id,
                                ]);
                            }
                        }
                    }
                }
            }

            // Recommendation strategy (at most one row per popup)
            $popup->targets()
                ->where('match_type', PopupTarget::MATCH_RECOMMENDATION_STRATEGY)
                ->delete();

            $strategySlug = $data['recommendation_strategy_slug'] ?? null;
            if (is_string($strategySlug) && $strategySlug !== '') {
                $popup->targets()->create([
                    'rule_type' => PopupTarget::RULE_INCLUDE,
                    'match_type' => PopupTarget::MATCH_RECOMMENDATION_STRATEGY,
                    'recommendation_strategy_slug' => $strategySlug,
                ]);
            }
        });
    }
}
