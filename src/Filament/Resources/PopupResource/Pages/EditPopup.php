<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedPopups\Filament\Resources\PopupResource;

class EditPopup extends EditRecord
{
//    use EditRecord\Concerns\Translatable;

    protected static string $resource = PopupResource::class;

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

    public function duplicate()
    {
        $newRecord = $this->record->replicate();
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
