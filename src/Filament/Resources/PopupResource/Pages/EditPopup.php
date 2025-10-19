<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
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
<<<<<<< HEAD
        while (\Dashed\DashedPopups\Models\Popup::where('name', $newRecord->name)->exists()) {
            $newRecord->name = $newRecord->name . '-copy';
        }
=======
        $newRecord->name = $newRecord->name . ' (kopie)';
>>>>>>> 1ad178eab92d6d3872b54d73b2982933155d5eb7
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
