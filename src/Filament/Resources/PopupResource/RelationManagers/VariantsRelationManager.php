<?php

namespace Dashed\DashedPopups\Filament\Resources\PopupResource\RelationManagers;

use Dashed\DashedPopups\Analytics\MetricsResolver;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Varianten';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('code_prefix')
                ->label('Code prefix')
                ->required()
                ->maxLength(20)
                ->helperText('Wordt gebruikt in de discount code, bijv. V1 geeft WELKOM-V1-XXXXX'),
            TextInput::make('split_weight')
                ->label('Split weight')
                ->numeric()
                ->default(50)
                ->required()
                ->helperText('Relatieve gewicht voor verdeling. 50/50 = gelijke split.'),
            TextInput::make('discount_percentage_override')
                ->label('Kortingspercentage override')
                ->numeric()
                ->minValue(1)
                ->maxValue(100)
                ->helperText('Leeg laten om het default popup-percentage te gebruiken.'),
            TextInput::make('discount_valid_days_override')
                ->label('Geldigheid (dagen) override')
                ->numeric()
                ->minValue(1)
                ->helperText('Leeg laten om de default popup-geldigheid te gebruiken.'),
            TextInput::make('sort_order')
                ->label('Sortering')
                ->numeric()
                ->default(0),
            Toggle::make('enabled')
                ->label('Actief')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam'),
                TextColumn::make('code_prefix')->label('Prefix')->badge(),
                TextColumn::make('split_weight')->label('Weight')->numeric(),
                TextColumn::make('discount_percentage_override')
                    ->label('Korting %')
                    ->state(fn ($record) => $record->discount_percentage_override
                        ? $record->discount_percentage_override.'%'
                        : 'Default'),
                TextColumn::make('views')
                    ->label('Views')
                    ->state(fn ($record) => app(MetricsResolver::class)
                        ->forPopupVariant($record->id, now()->subDays(30), now())['views']),
                TextColumn::make('submits')
                    ->label('Submits')
                    ->state(fn ($record) => app(MetricsResolver::class)
                        ->forPopupVariant($record->id, now()->subDays(30), now())['submits']),
                TextColumn::make('revenue')
                    ->label('Omzet')
                    ->badge()
                    ->color('success')
                    ->state(fn ($record) => '€ '.number_format(
                        app(MetricsResolver::class)->forPopupVariant($record->id, now()->subDays(30), now())['revenue'],
                        2,
                        ',',
                        '.'
                    )),
                IconColumn::make('enabled')->label('Actief')->boolean(),
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
