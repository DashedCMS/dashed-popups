<?php

namespace Dashed\DashedPopups\Filament\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class HeadingBlock
{
    public static function make(): Block
    {
        return Block::make('heading')
            ->label('Koptekst')
            ->icon('heroicon-o-h1')
            ->schema([
                TextInput::make('text')
                    ->label('Tekst')
                    ->required(),
                Select::make('level')
                    ->label('Niveau')
                    ->options(['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'])
                    ->default('h2'),
            ]);
    }
}
