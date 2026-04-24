<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Dashed\DashedPopups\Filament\Resources\PopupResource;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Concerns\SyncsPopupTargets;
use Dashed\DashedPopups\Filament\Widgets\PopupFunnelWidget;
use Dashed\DashedPopups\Models\Popup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class EditPopup extends EditRecord
{
    //    use EditRecord\Concerns\Translatable;
    use SyncsPopupTargets;

    protected static string $resource = PopupResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['title', 'blocks'] as $attribute) {
            $value = $this->record->{$attribute};
            $data[$attribute] = $value instanceof Collection ? $value->all() : $value;
        }

        // Pre-fill targeting form fields from stored targets
        $targets = $this->record->targets()->get();

        $data['include_url_patterns'] = $targets
            ->where('rule_type', 'include')
            ->where('match_type', 'url_pattern')
            ->map(fn ($t) => ['pattern' => $t->pattern])
            ->values()
            ->all();

        $data['exclude_url_patterns'] = $targets
            ->where('rule_type', 'exclude')
            ->where('match_type', 'url_pattern')
            ->map(fn ($t) => ['pattern' => $t->pattern])
            ->values()
            ->all();

        foreach (cms()->builder('routeModels') ?? [] as $key => $routeModel) {
            $modelClass = $routeModel['class'] ?? null;
            if (! $modelClass) {
                continue;
            }

            foreach (['include', 'exclude'] as $ruleType) {
                $sub = $targets->where('rule_type', $ruleType)->where('targetable_type', $modelClass);
                $allOfType = $sub->firstWhere('match_type', 'all_of_type');
                $specific = $sub->where('match_type', 'specific_model')->pluck('targetable_id')->all();

                if ($allOfType) {
                    $data["target_mode_{$ruleType}_{$key}"] = 'all';
                } elseif (count($specific)) {
                    $data["target_mode_{$ruleType}_{$key}"] = 'selected';
                    $data["target_ids_{$ruleType}_{$key}"] = $specific;
                } else {
                    $data["target_mode_{$ruleType}_{$key}"] = 'none';
                }
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncPopupTargets($this->record, $this->data);
    }

    protected function getActions(): array
    {
        return [
            //            LocaleSwitcher::make(),
            Action::make('duplicate')
                ->action('duplicate')
                ->button()
                ->label('Dupliceer'),
            DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PopupFunnelWidget::class,
        ];
    }

    protected function getHeaderWidgetsData(): array
    {
        return [
            'record' => $this->record,
        ];
    }

    public function getFooter(): ?View
    {
        return view('dashed-popups::filament.popups.edit-footer', ['record' => $this->record]);
    }

    public function duplicate()
    {
        $newRecord = $this->record->replicate();
        while (Popup::where('name', $newRecord->name)->exists()) {
            $newRecord->name = $newRecord->name.' (kopie)';
        }
        $newRecord->save();

        return redirect(route('filament.dashed.resources.popups.edit', [$newRecord]));
    }

    //    public function updatingActiveLocale($newVal): void
    //    {
    //        $this->oldActiveLocale = $this->activeLocale;
    //        $this->save();
    //
    //        foreach ($this->data['fields'] ?? [] as $key => $fieldArray) {
    //            $relation = $this->getRecord()->fields()->find($fieldArray['id'] ?? 0);
    //            if ($relation) {
    //                foreach ($relation->translatable as $attribute) {
    //                    $this->data['fields'][$key][$attribute] = $relation->getTranslation($attribute, $newVal);
    //                }
    //            }
    //        }
    //    }
}
