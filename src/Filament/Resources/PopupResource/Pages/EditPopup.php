<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Dashed\DashedPopups\Models\Popup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedPopups\Filament\Resources\PopupResource;
use Dashed\DashedPopups\Filament\Widgets\PopupFunnelWidget;
use Dashed\DashedPopups\Jobs\SyncPopupSubmissionToNewsletterJob;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Concerns\SyncsPopupTargets;

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
            Action::make('syncToNewsletter')
                ->label('Stuur eerder verzamelde emails door')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn ($record) => ! empty($record->api_subscriptions))
                ->disabled(fn ($record) => $this->totalSubmissionsCount($record) === 0)
                ->requiresConfirmation()
                ->modalHeading('Eerder verzamelde aanmeldingen doorsturen')
                ->modalDescription(fn ($record) => sprintf(
                    'Er staan %d aanmeldingen in totaal, waarvan %d nog nooit zijn doorgezet. Standaard worden alleen die nog-niet-verzonden inzendingen doorgezet. Schakel onderstaande toggle in om alles opnieuw door te sturen.',
                    $this->totalSubmissionsCount($record),
                    $this->pendingBackfillCount($record),
                ))
                ->schema([
                    Toggle::make('resendAll')
                        ->label('Alles opnieuw versturen (ook reeds verzonden inzendingen)')
                        ->helperText('Bij dubbele inzendingen rekent de nieuwsbriefdienst zelf af op duplicate-handling. Aan = forceer alles, uit = alleen nieuwe.')
                        ->default(false),
                ])
                ->action(fn ($record, array $data) => $this->dispatchNewsletterBackfill(
                    $record,
                    resendAll: (bool) ($data['resendAll'] ?? false),
                )),
            DeleteAction::make(),
        ];
    }

    protected function dispatchNewsletterBackfill($record, bool $resendAll = false): int
    {
        $count = 0;
        $query = $record->views()
            ->whereNotNull('submitted_at')
            ->whereNotNull('email');

        if (! $resendAll) {
            $query->whereNull('newsletter_synced_at');
        }

        $query->chunkById(50, function ($views) use (&$count, $resendAll) {
            foreach ($views as $view) {
                SyncPopupSubmissionToNewsletterJob::dispatch($view->id, $resendAll);
                $count++;
            }
        });

        Notification::make()
            ->title("{$count} aanmeldingen worden doorgezet")
            ->success()
            ->send();

        return $count;
    }

    protected function pendingBackfillCount($record): int
    {
        return $record->views()
            ->whereNotNull('submitted_at')
            ->whereNotNull('email')
            ->whereNull('newsletter_synced_at')
            ->count();
    }

    protected function totalSubmissionsCount($record): int
    {
        return $record->views()
            ->whereNotNull('submitted_at')
            ->whereNotNull('email')
            ->count();
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
