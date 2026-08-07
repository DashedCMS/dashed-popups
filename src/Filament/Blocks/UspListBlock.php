<?php

namespace Dashed\DashedPopups\Filament\Blocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;

class UspListBlock
{
    public static function make(): Block
    {
        return Block::make('usp_list')
            ->label(__('USP-lijst'))
            ->icon('heroicon-o-check-circle')
            ->schema([
                Repeater::make('items')
                    ->label(__('USPs'))
                    ->simple(TextInput::make('text')->required())
                    ->minItems(1)
                    ->defaultItems(3),
            ]);
    }
}
