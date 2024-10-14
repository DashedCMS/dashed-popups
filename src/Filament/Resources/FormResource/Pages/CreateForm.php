<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateForm extends CreateRecord
{
    use Translatable;

    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
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
}
