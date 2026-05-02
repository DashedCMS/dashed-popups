<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource;

class EditPopupFollowUpFlow extends EditRecord
{
    protected static string $resource = PopupFollowUpFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
