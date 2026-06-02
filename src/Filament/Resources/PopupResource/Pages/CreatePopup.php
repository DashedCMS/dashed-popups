<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedPopups\Filament\Resources\PopupResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Concerns\SyncsPopupTargets;

class CreatePopup extends CreateRecord
{
    //    use Translatable;
    use SyncsPopupTargets;

    protected static string $resource = PopupResource::class;

    protected function getActions(): array
    {
        return [
//            LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['mustHaveSomethingDefined']);
        foreach ($data as $key => $value) {
            if (str($key)->contains('redirect_after_popup')) {
                $key = str($key)->replace('redirect_after_popup_', '');
                $data['redirect_after_popup']['url_' . $key] = $data['redirect_after_popup_' . $key] ?? '';
                unset($data['redirect_after_popup_' . $key]);
            }
        }

        foreach (['minimal_requirements', 'valid_for'] as $noneable) {
            if (($data[$noneable] ?? null) === 'none' || ($data[$noneable] ?? null) === 'all') {
                $data[$noneable] = null;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncPopupTargets($this->record, $this->data);
        $this->record->discountProducts()->sync($this->data['discount_product_ids'] ?? []);
        $this->record->discountCategories()->sync($this->data['discount_category_ids'] ?? []);
    }
}
