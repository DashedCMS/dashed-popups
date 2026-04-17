<?php

namespace Dashed\DashedPopups\Filament\Blocks;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Builder\Block;

class ParagraphBlock
{
    public static function make(): Block
    {
        return Block::make('paragraph')
            ->label('Paragraaf')
            ->icon('heroicon-o-bars-3')
            ->schema([
                Textarea::make('text')
                    ->label('Tekst')
                    ->required()
                    ->rows(4),
            ]);
    }
}
