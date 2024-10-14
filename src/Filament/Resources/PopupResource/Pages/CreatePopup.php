<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedPopups\Filament\Resources\PopupResource;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreatePopup extends CreateRecord
{
//    use Translatable;

    protected static string $resource = PopupResource::class;

    protected function getActions(): array
    {
        return [
//            LocaleSwitcher::make(),
        ];
    }

    protected function mutatePopupDataBeforeCreate(array $data): array
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
}
