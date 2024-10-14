<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedForms\Filament\Resources\FormResource;

class EditForm extends EditRecord
{
    use EditRecord\Concerns\Translatable;

    protected static string $resource = FormResource::class;

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
            $newField->form_id = $newRecord->id;
            $newField->save();
        }

        return redirect(route('filament.dashed.resources.forms.edit', [$newRecord]));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['mustHaveSomethingDefined']);
        foreach ($data as $key => $value) {
            if (str($key)->contains('redirect_after_form')) {
                $key = str($key)->replace('redirect_after_form_', '');
                $data['redirect_after_form']['url_' . $key] = $data['redirect_after_form_' . $key] ?? '';
                unset($data['redirect_after_form_' . $key]);
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($data['redirect_after_form'] ?? [] as $key => $value) {
            $data['redirect_after_form_' . str($key)->replace('url_', '')] = $value;
        }

        unset($data['redirect_after_form']);

        return parent::mutateFormDataBeforeFill($data);
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
