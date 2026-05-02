<?php

namespace Dashed\DashedPopups\Services;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Dashed\DashedPopups\Models\Popup;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedPopups\Models\PopupTarget;

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
        $candidate = $request->attributes->get('dashed.current_visitable');

        return $candidate instanceof Model ? $candidate : null;
    }

    protected function matches(PopupTarget $target, string $path, ?Model $model): bool
    {
        if ($target->match_type === 'url_pattern' && $target->pattern) {
            return Str::is($this->normalizePath($target->pattern), $path);
        }

        if (! $model || ! $target->targetable_type) {
            return false;
        }

        if ($target->match_type === 'all_of_type') {
            return $model instanceof $target->targetable_type;
        }

        if ($target->match_type === 'specific_model') {
            return $model instanceof $target->targetable_type
                && (string) $model->getKey() === (string) $target->targetable_id;
        }

        return false;
    }
}
