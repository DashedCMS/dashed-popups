<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Resources\RelationManagers\RelationManager;

class ConversionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conversions';

    protected static ?string $title = 'Conversies';

    protected static ?string $recordTitleAttribute = 'submitted_at';

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('submitted_at')->label(__('Tijdstip'))->dateTime('d-m-Y H:i'),
            TextEntry::make('discountCode.code')->label(__('Kortingscode'))->copyable(),
            TextEntry::make('ip_address')->label(__('IP')),
            TextEntry::make('matched_order_id')
                ->label(__('Gekoppelde order'))
                ->formatStateUsing(function ($state, $record) {
                    if (! $state) {
                        return 'Nog geen order gekoppeld';
                    }
                    $invoice = $record->matchedOrder?->invoice_id;

                    return $invoice ? 'Order #'.$invoice : 'Order #'.$state;
                })
                ->url(function ($record) {
                    if (! $record->matched_order_id) {
                        return null;
                    }
                    if (! class_exists(\Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::class)) {
                        return null;
                    }

                    return \Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->matched_order_id]);
                }, shouldOpenInNewTab: true),
            KeyValueEntry::make('content')->label(__('Ingevoerde gegevens')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('submitted_at')
                    ->label(__('Tijdstip'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                TextColumn::make('content.email')
                    ->label(__('Email'))
                    ->copyable()
                    ->searchable(),
                IconColumn::make('matched_order_id')
                    ->label(__('Order'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->tooltip(fn ($record) => $record->matched_order_id
                        ? __('Order #:nummer', ['nummer' => $record->matchedOrder?->invoice_id ?? $record->matched_order_id])
                        : __('Nog geen order gekoppeld'))
                    ->url(function ($record) {
                        if (! $record->matched_order_id) {
                            return null;
                        }
                        if (! class_exists(\Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::class)) {
                            return null;
                        }

                        return \Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record->matched_order_id]);
                    }, shouldOpenInNewTab: true),
                TextColumn::make('follow_up_status')
                    ->label(__('Follow-up flow'))
                    ->state(fn ($record) => match ($record->followUpStatus()) {
                        'cancelled' => 'Geannuleerd',
                        'not_in_flow' => 'Niet in flow',
                        'finished' => 'Afgerond',
                        default => str_replace(['step_', '_of_'], ['Stap ', ' van '], $record->followUpStatus()),
                    })
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        str_starts_with($record->followUpStatus(), 'step_') => 'warning',
                        $record->followUpStatus() === 'finished' => 'success',
                        $record->followUpStatus() === 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('discountCode.code')
                    ->label(__('Kortingscode'))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label(__('IP'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('has_discount_code')
                    ->label(__('Heeft kortingscode'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('discount_code_id'),
                        false: fn (Builder $q) => $q->whereNull('discount_code_id'),
                    ),
                TernaryFilter::make('has_matched_order')
                    ->label(__('Heeft order'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('matched_order_id'),
                        false: fn (Builder $q) => $q->whereNull('matched_order_id'),
                    ),
                Filter::make('submitted_at')
                    ->schema([
                        DatePicker::make('from')->label(__('Vanaf')),
                        DatePicker::make('until')->label(__('Tot en met')),
                    ])
                    ->query(function (Builder $q, array $data): Builder {
                        return $q
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('submitted_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('submitted_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
