<?php

namespace Dashed\DashedPopups\Filament\Blocks;

use Dashed\DashedPopups\Models\Popup;

class PopupBlockRegistry
{
    public static function allowedBlocksFor(Popup $popup): array
    {
        $blocks = [
            HeadingBlock::make(),
            ParagraphBlock::make(),
            ImageBlock::make(),
            UspListBlock::make(),
        ];

        if ($popup->type === 'discount') {
            $blocks[] = DiscountHighlightBlock::make();
        }

        return $blocks;
    }
}
