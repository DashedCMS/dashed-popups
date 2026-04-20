<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Dashed\DashedPopups\Filament\Resources\PopupResource;
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

    protected static string $resource = PopupResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['title', 'blocks'] as $attribute) {
            $value = $this->record->{$attribute};
            $data[$attribute] = $value instanceof Collection ? $value->all() : $value;
        }

        return $data;
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
