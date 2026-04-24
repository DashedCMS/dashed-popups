<?php

namespace Dashed\DashedPopups\Services;

use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\Models\PopupTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PopupTargetingService
{
    public function shouldShow(Popup $popup, Request $request): bool
    {
        $path = $this->normalizePath($request->path());
        $model = $this->resolveCurrentModel($request);

        foreach ($popup->excludeTargets as $target) {
            if ($this->matches($target, $path, $model)) {
                return false;
            }
        }

        if ($popup->visibility_mode === 'only_selection') {
            foreach ($popup->includeTargets as $target) {
                if ($this->matches($target, $path, $model)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    protected function normalizePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $supportedLocales = (array) config('app.supported_locales', []);
        $segments = explode('/', ltrim($path, '/'));
        if (count($segments) && in_array($segments[0], $supportedLocales, true)) {
            array_shift($segments);
        }

        return '/'.implode('/', $segments);
    }

    protected function resolveCurrentModel(Request $request): ?Model
    {
        if ($request->attributes->has('dashed.current_visitable')) {
            return $request->attributes->get('dashed.current_visitable');
        }
        if (app()->bound('dashed.current_visitable')) {
            return app('dashed.current_visitable');
        }

        return null;
    }

    protected function matches(PopupTarget $target, string $path, ?Model $model): bool
    {
        if ($target->match_type === 'url_pattern' && $target->pattern) {
            return Str::is($target->pattern, $path);
        }

        if (! $model || ! $target->targetable_type) {
            return false;
        }

        if ($target->match_type === 'all_of_type') {
            return $model instanceof $target->targetable_type;
        }

        if ($target->match_type === 'specific_model') {
            return $model instanceof $target->targetable_type
                && (int) $model->getKey() === (int) $target->targetable_id;
        }

        return false;
    }
}
