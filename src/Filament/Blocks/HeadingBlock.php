<?php

namespace Dashed\DashedPopups\Filament\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Builder\Block;

class HeadingBlock
{
    public static function make(): Block
    {
        return Block::make('heading')
            ->label(__('Koptekst'))
            ->icon('heroicon-o-h1')
            ->schema([
                TextInput::make('text')
                    ->label(__('Tekst'))
                    ->required(),
                Select::make('level')
                    ->label(__('Niveau'))
                    ->options(['h1' => __('H1'), 'h2' => __('H2'), 'h3' => __('H3')])
                    ->default('h2'),
            ]);
    }
}
