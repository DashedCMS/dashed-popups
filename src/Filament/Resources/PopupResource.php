<?php

namespace Dashed\DashedPopups\Filament\Resources;

use BackedEnum;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedPopups\Analytics\MetricsResolver;
use Dashed\DashedPopups\Analytics\StatusClassifier;
use Dashed\DashedPopups\Filament\Blocks\PopupBlockRegistry;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\CreatePopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\EditPopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\ListPopups;
use Dashed\DashedPopups\Filament\Resources\PopupResource\RelationManagers\ConversionsRelationManager;
use Dashed\DashedPopups\Filament\Resources\PopupResource\RelationManagers\VariantsRelationManager;
use Dashed\DashedPopups\Models\Popup;
use Dashed\DashedPopups\PopupTemplates\PopupTemplateRegistry;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class PopupResource extends Resource
{
    protected static ?string $model = Popup::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

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
                    Toggle::make('notify_on_conversion')
                        ->label('Stuur Telegram-notificatie bij conversie')
                        ->helperText('Gebruikt de algemene Telegram-bot uit instellingen.')
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

            Section::make('Weergave')
                ->schema([
                    Radio::make('visibility_mode')
                        ->label('Waar tonen?')
                        ->options([
                            'everywhere' => 'Overal',
                            'only_selection' => 'Alleen op de selectie hieronder',
                        ])
                        ->default('everywhere')
                        ->required()
                        ->live()
                        ->columnSpanFull(),

                    Section::make('Tonen op')
                        ->visible(fn (Get $get) => $get('visibility_mode') === 'only_selection')
                        ->schema([
                            Repeater::make('include_url_patterns')
                                ->label('URL-patronen')
                                ->helperText('Bijvoorbeeld /shop/*, /checkout')
                                ->simple(
                                    TextInput::make('pattern')
                                        ->placeholder('/shop/*')
                                        ->required()
                                )
                                ->dehydrated(false)
                                ->columnSpanFull(),

                            static::modelTargetingFieldset('include'),
                        ])
                        ->columnSpanFull(),

                    Section::make('Uitsluiten op')
                        ->schema([
                            Repeater::make('exclude_url_patterns')
                                ->label('URL-patronen')
                                ->helperText('Deze winnen altijd van include-regels')
                                ->simple(
                                    TextInput::make('pattern')
                                        ->placeholder('/checkout')
                                        ->required()
                                )
                                ->dehydrated(false)
                                ->columnSpanFull(),

                            static::modelTargetingFieldset('exclude'),
                        ])
                        ->columnSpanFull(),
                ])
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

                        return $views > 0 ? round(($submits / $views) * 100, 1).'%' : '-';
                    }),
                TextColumn::make('dismissals_count')
                    ->label('Wegklik')
                    ->counts(['views as dismissals_count' => fn ($q) => $q->whereNotNull('closed_at')->whereNull('submitted_at')])
                    ->sortable(),
                TextColumn::make('dismissal_rate')
                    ->label('Wegklik %')
                    ->getStateUsing(function ($record) {
                        $views = (int) ($record->views_count ?? 0);
                        $dismissals = (int) ($record->dismissals_count ?? 0);

                        return $views > 0 ? round(($dismissals / $views) * 100, 1).'%' : '-';
                    }),
                TextColumn::make('overall_status_30d')
                    ->label('Status (30d)')
                    ->badge()
                    ->colors([
                        'success' => 'Goed',
                        'warning' => 'Matig',
                        'danger' => 'Slecht',
                        'gray' => fn ($state) => in_array($state, ['Voldoende', 'Weinig data']),
                    ])
                    ->getStateUsing(function ($record) {
                        return Cache::remember(
                            "popup-list-status:{$record->id}",
                            300,
                            function () use ($record) {
                                $m = app(MetricsResolver::class)
                                    ->forPopup($record->id, now()->subDays(29), now());
                                $s = app(StatusClassifier::class)->classify($m);

                                return [
                                    'excellent' => 'Goed',
                                    'ok' => 'Voldoende',
                                    'mediocre' => 'Matig',
                                    'poor' => 'Slecht',
                                    'insufficient_data' => 'Weinig data',
                                ][$s['overall']] ?? '-';
                            }
                        );
                    }),
                TextColumn::make('bounce_rate_30d')
                    ->label('Bounce (30d)')
                    ->getStateUsing(function ($record) {
                        return Cache::remember(
                            "popup-list-bounce:{$record->id}",
                            300,
                            function () use ($record) {
                                $m = app(MetricsResolver::class)
                                    ->forPopup($record->id, now()->subDays(29), now());

                                return $m['views'] > 0 ? number_format($m['bounce_rate'] * 100, 1).'%' : '-';
                            }
                        );
                    }),
                TextColumn::make('revenue_30d')
                    ->label('Omzet (30d)')
                    ->alignment('right')
                    ->sortable(false)
                    ->getStateUsing(function ($record) {
                        return Cache::remember(
                            "popup-list-revenue:{$record->id}",
                            300,
                            function () use ($record) {
                                $m = app(MetricsResolver::class)
                                    ->forPopup($record->id, now()->subDays(29), now());

                                return $m['revenue'] > 0
                                    ? CurrencyHelper::formatPrice($m['revenue'])
                                    : '-';
                            }
                        );
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
        return [
            ConversionsRelationManager::class,
            VariantsRelationManager::class,
        ];
    }

    protected static function modelTargetingFieldset(string $ruleType): Fieldset
    {
        $fields = [];
        foreach (cms()->builder('routeModels') ?? [] as $key => $routeModel) {
            $modelClass = $routeModel['class'] ?? null;
            if (! $modelClass || ! class_exists($modelClass)) {
                continue;
            }
            $label = $routeModel['name'] ?? $key;
            $nameField = $routeModel['nameField'] ?? 'name';

            $fields[] = Radio::make("target_mode_{$ruleType}_{$key}")
                ->label("Zichtbaar op {$label}")
                ->options([
                    'none' => 'Geen beperking',
                    'all' => "Alle {$label}",
                    'selected' => 'Geselecteerde items',
                ])
                ->default('none')
                ->live()
                ->dehydrated(false)
                ->columnSpanFull();

            $fields[] = Select::make("target_ids_{$ruleType}_{$key}")
                ->label("Selecteer {$label}")
                ->multiple()
                ->searchable()
                ->options(fn () => $modelClass::query()->limit(200)->pluck($nameField, 'id')->all())
                ->visible(fn (Get $get) => $get("target_mode_{$ruleType}_{$key}") === 'selected')
                ->dehydrated(false)
                ->columnSpanFull();
        }

        return Fieldset::make('Per modeltype')
            ->schema($fields)
            ->columns(1);
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
