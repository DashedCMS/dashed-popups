<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedPopups\Filament\Resources\PopupResource;

class EditPopup extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = PopupResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
            Action::make('duplicate')
                ->action('duplicate')
                ->button()
                ->label('Dupliceer'),
            DeleteAction::make(),
        ];
    }

    public function duplicate()
    {
        $newRecord = $this->record->replicate();
        $newRecord->save();

        foreach ($this->record->fields as $field) {
            $newField = $field->replicate();
            $newField->popup_id = $newRecord->id;
            $newField->save();
        }

        return redirect(route('filament.dashed.resources.popups.edit', [$newRecord]));
    }

    protected function mutatePopupDataBeforeSave(array $data): array
    {
        unset($data['mustHaveSomethingDefined']);
        foreach ($data as $key => $value) {
            if (str($key)->contains('redirect_after_popup')) {
                $key = str($key)->replace('redirect_after_popup_', '');
                $data['redirect_after_popup']['url_' . $key] = $data['redirect_after_popup_' . $key] ?? '';
                unset($data['redirect_after_popup_' . $key]);
            }
        }

        return $data;
    }

    protected function mutatePopupDataBeforeFill(array $data): array
    {
        foreach ($data['redirect_after_popup'] ?? [] as $key => $value) {
            $data['redirect_after_popup_' . str($key)->replace('url_', '')] = $value;
        }

        unset($data['redirect_after_popup']);

        return parent::mutatePopupDataBeforeFill($data);
    }

    public function updatingActiveLocale($newVal): void
    {
        $this->oldActiveLocale = $this->activeLocale;
        $this->save();

        foreach ($this->data['fields'] ?? [] as $key => $fieldArray) {
            $relation = $this->getRecord()->fields()->find($fieldArray['id'] ?? 0);
            if ($relation) {
                foreach ($relation->translatable as $attribute) {
                    $this->data['fields'][$key][$attribute] = $relation->getTranslation($attribute, $newVal);
                }
            }
        }
    }
}
