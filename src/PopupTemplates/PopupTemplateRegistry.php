<?php

namespace Dashed\DashedPopups\PopupTemplates;

class PopupTemplateRegistry
{
    protected static array $templates = [];

    public static function register(string $key, array $template): void
    {
        self::$templates[$key] = $template;
    }

    public static function all(): array
    {
        return self::$templates;
    }

    public static function options(): array
    {
        return collect(self::$templates)
            ->map(fn ($tpl) => $tpl['label'] ?? $tpl['name'] ?? '')
            ->all();
    }

    public static function blocksFor(string $key): ?array
    {
        return self::$templates[$key]['blocks'] ?? null;
    }

    public static function attributesFor(string $key): ?array
    {
        return self::$templates[$key]['attributes'] ?? null;
    }

    public static function clear(): void
    {
        self::$templates = [];
    }
}
