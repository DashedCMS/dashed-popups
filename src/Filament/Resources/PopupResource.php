<?php

namespace Dashed\DashedPopups\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Dashed\DashedPopups\Models\Popup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Builder;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedPopups\Filament\Blocks\PopupBlockRegistry;
use Dashed\DashedPopups\PopupTemplates\PopupTemplateRegistry;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\EditPopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\ListPopups;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\CreatePopup;

class PopupResource extends Resource
{
    protected static ?string $model = Popup::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';

    protected static string | UnitEnum | null $navigationGroup = 'Content';

    protected static ?string $label = 'Popup';

    protected static ?string $pluralLabel = 'Popups';

    protected static bool $isGloballySearchable = false;

    public static function getNavigationLabel(): string
    {
        return 'Popups';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Type')
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options([
                            'simple' => 'Simpel',
                            'discount' => 'Korting + email-capture',
                        ])
                        ->default('simple')
                        ->required()
                        ->live(),
                    Select::make('_start_from_template')
                        ->label('Begin vanaf standaard-template')
                        ->options(PopupTemplateRegistry::options())
                        ->dehydrated(false)
                        ->visible(fn (?Popup $record) => $record === null)
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $state) {
                                return;
                            }

                            foreach (PopupTemplateRegistry::attributesFor($state) ?? [] as $key => $value) {
                                $set($key, $value);
                            }

                            if ($blocks = PopupTemplateRegistry::blocksFor($state)) {
                                $set('blocks', $blocks);
                            }
                        }),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Inhoud')
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('title')
                        ->label('Kop')
                        ->columnSpanFull(),
                    Builder::make('blocks')
                        ->label('Inhoud-blokken')
                        ->blocks(fn (?Popup $record) => PopupBlockRegistry::allowedBlocksFor($record ?? new Popup(['type' => 'simple'])))
                        ->collapsible()
                        ->cloneable()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Korting')
                ->visible(fn (Get $get) => $get('type') === 'discount')
                ->schema([
                    TextInput::make('discount_percentage')
                        ->label('Kortingspercentage')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(99)
                        ->default(10)
                        ->required(),
                    TextInput::make('discount_valid_days')
                        ->label('Geldig voor (dagen)')
                        ->numeric()
                        ->minValue(1)
                        ->default(14),
                    Toggle::make('auto_apply_discount')
                        ->label('Automatisch toepassen op winkelmand')
                        ->default(true),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make('Trigger')
                ->schema([
                    Select::make('trigger_type')
                        ->label('Wanneer tonen')
                        ->options([
                            'delay' => 'Tijdsvertraging',
                            'scroll' => 'Scroll-diepte',
                            'exit_intent' => 'Exit-intent',
                        ])
                        ->default('delay')
                        ->live()
                        ->required(),
                    TextInput::make('trigger_value')
                        ->numeric()
                        ->default(5)
                        ->label(fn (Get $get) => match ($get('trigger_type')) {
                            'scroll' => 'Scroll %',
                            default => 'Seconden',
                        })
                        ->visible(fn (Get $get) => in_array($get('trigger_type'), ['delay', 'scroll'], true)),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Display')
                ->schema([
                    Toggle::make('active')
                        ->label('Actief')
                        ->default(false),
                    DateTimePicker::make('start_date')
                        ->label('Start datum')
                        ->default(now())
                        ->required(),
                    DateTimePicker::make('end_date')
                        ->label('Eind datum')
                        ->default(now()->addYear())
                        ->required(),
                    TextInput::make('show_again_after')
                        ->label('Opnieuw tonen na (minuten)')
                        ->helperText('20160 = 14 dagen')
                        ->default(20160)
                        ->required()
                        ->numeric(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Actief')
                    ->boolean(),
                TextColumn::make('views_count')
                    ->label('Impressies')
                    ->counts('views')
                    ->sortable(),
                TextColumn::make('submits_count')
                    ->label('Submits')
                    ->counts(['views as submits_count' => fn ($q) => $q->whereNotNull('submitted_at')])
                    ->sortable(),
                TextColumn::make('conversion')
                    ->label('Conversie')
                    ->getStateUsing(function ($record) {
                        $views = (int) ($record->views_count ?? 0);
                        $submits = (int) ($record->submits_count ?? 0);

                        return $views > 0 ? round(($submits / $views) * 100, 1) . '%' : '-';
                    }),
            ])
            ->recordActions([
                EditAction::make()->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions())
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPopups::route('/'),
            'create' => CreatePopup::route('/create'),
            'edit' => EditPopup::route('/{record}/edit'),
        ];
    }
}
