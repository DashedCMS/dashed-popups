<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Dashed\DashedPopups\Services\BackfillPopupFollowUpFlowService;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource;

class EditPopupFollowUpFlow extends EditRecord
{
    protected static string $resource = PopupFollowUpFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backfillExisting')
                ->label(__('Toepassen op bestaande'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->modalHeading(__('Flow toepassen op bestaande popup-conversies'))
                ->modalDescription(__('Plant alsnog de emails van deze flow voor PopupViews waarvan de bezoeker al een email heeft ingevuld maar nog niet in een follow-up flow zit. Records die al een follow-up gestart of geannuleerd hebben worden overgeslagen.'))
                ->form([
                    TextInput::make('since_days')
                        ->label(__('Aantal dagen terug'))
                        ->helperText(__('Backfill geldt voor PopupViews waarvan submitted_at binnen de afgelopen X dagen valt.'))
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
                        ->title(__('Backfill voltooid'))
                        ->body(__('Gestart: :started. Al gestart: :alreadyStarted. Geannuleerd: :cancelled. Geen email: :noEmail. Emails ingepland: :scheduled.', [
                            'started' => $stats['views_started'],
                            'alreadyStarted' => $stats['views_skipped_already_started'],
                            'cancelled' => $stats['views_skipped_cancelled'],
                            'noEmail' => $stats['views_skipped_no_email'],
                            'scheduled' => $stats['emails_dispatched'],
                        ]))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
