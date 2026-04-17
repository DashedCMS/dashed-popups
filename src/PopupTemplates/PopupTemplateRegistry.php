<?php

namespace Dashed\DashedPopups\PopupTemplates;

class PopupTemplateRegistry
{
    public static function all(): array
    {
        return [
            'welkom' => [
                'label' => 'Welkom: 10% korting',
                'blocks' => [
                    ['type' => 'heading', 'data' => ['text' => 'Welkom!', 'level' => 'h2']],
                    ['type' => 'discount_highlight', 'data' => ['label' => 'Krijg nu', 'value' => '10%', 'suffix' => 'korting']],
                    ['type' => 'paragraph', 'data' => ['text' => 'Vul je e-mailadres in en ontvang direct een kortingscode.']],
                ],
            ],
            'exit_intent' => [
                'label' => 'Exit-intent: laatste kans',
                'blocks' => [
                    ['type' => 'heading', 'data' => ['text' => 'Wacht nog even!', 'level' => 'h2']],
                    ['type' => 'paragraph', 'data' => ['text' => 'Voor je gaat: hier is een laatste kans op korting.']],
                    ['type' => 'discount_highlight', 'data' => ['label' => 'Speciaal voor jou', 'value' => '10%', 'suffix' => 'korting']],
                    ['type' => 'usp_list', 'data' => ['items' => [
                        ['text' => 'Geldig op alle producten'],
                        ['text' => '14 dagen geldig'],
                        ['text' => 'Eenmalig te gebruiken'],
                    ]]],
                ],
            ],
            'seasonal' => [
                'label' => 'Seasonal: Black Friday / kerst',
                'blocks' => [
                    ['type' => 'heading', 'data' => ['text' => 'Speciale aanbieding', 'level' => 'h2']],
                    ['type' => 'paragraph', 'data' => ['text' => 'Voeg hier je seizoenstekst toe.']],
                    ['type' => 'discount_highlight', 'data' => ['label' => 'Tijdelijk', 'value' => '10%', 'suffix' => 'korting']],
                ],
            ],
        ];
    }

    public static function options(): array
    {
        return collect(self::all())
            ->map(fn ($tpl) => $tpl['label'])
            ->all();
    }

    public static function blocksFor(string $key): ?array
    {
        return self::all()[$key]['blocks'] ?? null;
    }
}
