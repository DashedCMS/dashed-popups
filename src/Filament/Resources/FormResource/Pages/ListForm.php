<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Dashed\DashedForms\Classes\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedForms\Filament\Resources\FormResource;

class ListForm extends ListRecords
{
    protected static string $resource = FormResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('createContactForm')
                ->label('Contact formulier aanmaken')
                ->action(function () {
                    Forms::createPresetForms('contact');
                    Notification::make()
                        ->title('Contact formulier aangemaakt')
                        ->success()
                        ->send();
                }),
        ];
    }
}
