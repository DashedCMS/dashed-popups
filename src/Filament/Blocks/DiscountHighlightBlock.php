<?php

namespace Dashed\DashedPopups\Filament\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;

class DiscountHighlightBlock
{
    public static function make(): Block
    {
        return Block::make('discount_highlight')
            ->label('Korting-highlight')
            ->icon('heroicon-o-tag')
            ->schema([
                TextInput::make('label')
                    ->label('Label boven')
                    ->default('Krijg nu'),
                TextInput::make('value')
                    ->label('Hoofdwaarde (bijv. "10%")')
                    ->required(),
                TextInput::make('suffix')
                    ->label('Label onder')
                    ->default('Korting'),
            ]);
    }
}
