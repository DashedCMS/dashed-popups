<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource;

class ListPopupFollowUpFlows extends ListRecords
{
    protected static string $resource = PopupFollowUpFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nieuwe flow'),
        ];
    }
}
