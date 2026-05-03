<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages;

use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Dashed\DashedPopups\Services\BackfillPopupFollowUpFlowService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPopupFollowUpFlow extends EditRecord
{
    protected static string $resource = PopupFollowUpFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backfillExisting')
                ->label('Toepassen op bestaande')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->modalHeading('Flow toepassen op bestaande popup-conversies')
                ->modalDescription('Plant alsnog de emails van deze flow voor PopupViews waarvan de bezoeker al een email heeft ingevuld maar nog niet in een follow-up flow zit. Records die al een follow-up gestart of geannuleerd hebben worden overgeslagen.')
                ->form([
                    TextInput::make('since_days')
                        ->label('Aantal dagen terug')
                        ->helperText('Backfill geldt voor PopupViews waarvan submitted_at binnen de afgelopen X dagen valt.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var PopupFollowUpFlow $flow */
                    $flow = $this->record;

                    $stats = app(BackfillPopupFollowUpFlowService::class)->run(
                        flow: $flow,
                        sinceDays: (int) ($data['since_days'] ?? 30),
                    );

                    Notification::make()
                        ->title('Backfill voltooid')
                        ->body(sprintf(
                            'Gestart: %d. Al gestart: %d. Geannuleerd: %d. Geen email: %d. Emails ingepland: %d.',
                            $stats['views_started'],
                            $stats['views_skipped_already_started'],
                            $stats['views_skipped_cancelled'],
                            $stats['views_skipped_no_email'],
                            $stats['emails_dispatched'],
                        ))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
