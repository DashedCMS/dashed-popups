<?php

namespace Dashed\DashedPopups\Filament\Blocks;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;

class DiscountHighlightBlock
{
    public static function make(): Block
    {
        return Block::make('discount_highlight')
            ->label(__('Korting-highlight'))
            ->icon('heroicon-o-tag')
            ->schema([
                TextInput::make('label')
                    ->label(__('Label boven'))
                    ->default('Krijg nu'),
                TextInput::make('value')
                    ->label(__('Hoofdwaarde (bijv. "10%")'))
                    ->required(),
                TextInput::make('suffix')
                    ->label(__('Label onder'))
                    ->default('Korting'),
            ]);
    }
}
