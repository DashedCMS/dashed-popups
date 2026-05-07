<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Dashed\DashedPopups\Models\Popup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedPopups\Filament\Resources\PopupResource;
use Dashed\DashedPopups\PopupTemplates\PopupTemplateRegistry;
use Dashed\DashedPopups\Filament\Widgets\PopupPerformanceOverview;

class ListPopups extends ListRecords
{
    protected static string $resource = PopupResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PopupPerformanceOverview::class,
        ];
    }

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('generateFromTemplate')
                ->label('Genereer standaard popup')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn () => ! empty(PopupTemplateRegistry::options()))
                ->form([
                    Select::make('template')
                        ->label('Template')
                        ->options(PopupTemplateRegistry::options())
                        ->required(),
                    TextInput::make('name')
                        ->label('Naam')
                        ->required()
                        ->default(fn () => 'standaard-'.now()->format('Y-m-d-His')),
                ])
                ->action(function (array $data) {
                    $attributes = PopupTemplateRegistry::attributesFor($data['template']) ?? [];
                    $blocks = PopupTemplateRegistry::blocksFor($data['template']) ?? [];

                    $popup = Popup::create(array_merge([
                        'name' => $data['name'],
                        'start_date' => now(),
                        'end_date' => now()->addYear(),
                    ], $attributes, ['blocks' => $blocks]));

                    Notification::make()
                        ->title('Standaard popup aangemaakt')
                        ->body('Je kunt hem nu aanpassen voordat je hem activeert.')
                        ->success()
                        ->send();

                    $this->redirect(PopupResource::getUrl('edit', ['record' => $popup]));
                }),
        ];
    }
}
