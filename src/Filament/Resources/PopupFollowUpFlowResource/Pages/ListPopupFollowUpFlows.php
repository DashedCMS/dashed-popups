<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource;

class ListPopupFollowUpFlows extends ListRecords
{
    protected static string $resource = PopupFollowUpFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_default')
                ->label('Maak standaard flow aan')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Standaard flow aanmaken')
                ->modalDescription('Dit maakt een nieuwe flow aan met 3 stappen (1 uur, 24 uur en 72 uur na conversie) en stelt deze in als actieve standaard flow. Andere flows worden automatisch op inactive en niet-standaard gezet.')
                ->modalSubmitActionLabel('Aanmaken')
                ->action(function () {
                    PopupFollowUpFlow::createDefault();

                    Notification::make()
                        ->title('Standaard flow aangemaakt en geactiveerd')
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Nieuwe flow'),
        ];
    }
}
