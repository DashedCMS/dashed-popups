<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Dashed\DashedPopups\Analytics\MetricsResolver;
use Filament\Resources\RelationManagers\RelationManager;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Varianten';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('code_prefix')
                ->label(__('Code prefix'))
                ->required()
                ->maxLength(20)
                ->helperText(__('Wordt gebruikt in de discount code, bijv. V1 geeft WELKOM-V1-XXXXX')),
            TextInput::make('split_weight')
                ->label(__('Split weight'))
                ->numeric()
                ->default(50)
                ->required()
                ->helperText(__('Relatieve gewicht voor verdeling. 50/50 = gelijke split.')),
            TextInput::make('discount_percentage_override')
                ->label(__('Kortingspercentage override'))
                ->numeric()
                ->step(0.01)
                ->minValue(0.01)
                ->maxValue(99.99)
                ->helperText(__('Decimalen toegestaan (bijv. 12.5). Leeg laten om het default popup-percentage te gebruiken.')),
            TextInput::make('discount_valid_days_override')
                ->label(__('Geldigheid (dagen) override'))
                ->numeric()
                ->minValue(1)
                ->helperText(__('Leeg laten om de default popup-geldigheid te gebruiken.')),
            TextInput::make('sort_order')
                ->label(__('Sortering'))
                ->numeric()
                ->default(0),
            Toggle::make('enabled')
                ->label(__('Actief'))
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Naam')),
                TextColumn::make('code_prefix')->label(__('Prefix'))->badge(),
                TextColumn::make('split_weight')->label(__('Weight'))->numeric(),
                TextColumn::make('discount_percentage_override')
                    ->label(__('Korting %'))
                    ->state(fn ($record) => $record->discount_percentage_override
                        ? $record->discount_percentage_override.'%'
                        : 'Default'),
                TextColumn::make('views')
                    ->label(__('Views'))
                    ->state(fn ($record) => app(MetricsResolver::class)
                        ->forPopupVariant($record->id, now()->subDays(30), now())['views']),
                TextColumn::make('submits')
                    ->label(__('Submits'))
                    ->state(fn ($record) => app(MetricsResolver::class)
                        ->forPopupVariant($record->id, now()->subDays(30), now())['submits']),
                TextColumn::make('revenue')
                    ->label(__('Omzet'))
                    ->badge()
                    ->color('success')
                    ->state(fn ($record) => '€ '.number_format(
                        app(MetricsResolver::class)->forPopupVariant($record->id, now()->subDays(30), now())['revenue'],
                        2,
                        ',',
                        '.'
                    )),
                IconColumn::make('enabled')->label(__('Actief'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
