<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedPopups\Filament\Resources\PopupResource;

class ListPopups extends ListRecords
{
    protected static string $resource = PopupResource::class;

    protected function getActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
