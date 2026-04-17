<?php

namespace Dashed\DashedPopups\Filament\Blocks;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Builder\Block;

class ImageBlock
{
    public static function make(): Block
    {
        return Block::make('image')
            ->label('Afbeelding')
            ->icon('heroicon-o-photo')
            ->schema([
                FileUpload::make('image')
                    ->image()
                    ->directory('popups')
                    ->required(),
                TextInput::make('alt')
                    ->label('Alt-tekst'),
            ]);
    }
}
