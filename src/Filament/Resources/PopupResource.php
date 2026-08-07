<?php

namespace Dashed\DashedPopups\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Dashed\DashedPopups\Models\Popup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Cache;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedPopups\Analytics\MetricsResolver;
use Dashed\DashedPopups\Analytics\StatusClassifier;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedPopups\Filament\Blocks\PopupBlockRegistry;
use Dashed\DashedPopups\PopupTemplates\PopupTemplateRegistry;
use Dashed\DashedCore\Classes\Actions\ActionGroups\ToolbarActions;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\EditPopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\ListPopups;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\CreatePopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\RelationManagers\VariantsRelationManager;
use Dashed\DashedPopups\Filament\Resources\PopupResource\RelationManagers\ConversionsRelationManager;

class PopupResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\HasLastEditedColumn;

    protected static ?string $model = Popup::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Communicatie';

    protected static ?int $navigationSort = 20;

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
            Section::make(__('Type'))
                ->schema([
                    Select::make('type')
                        ->label(__('Type'))
                        ->options([
                            'simple' => __('Simpel'),
                            'discount' => __('Korting + email-capture'),
                        ])
                        ->default('simple')
                        ->required()
                        ->live(),
                    Select::make('_start_from_template')
                        ->label(__('Begin vanaf standaard-template'))
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

            Section::make(__('Inhoud'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Naam'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('title')
                        ->label(__('Kop'))
                        ->columnSpanFull(),
                    Builder::make('blocks')
                        ->label(__('Inhoud-blokken'))
                        ->blocks(fn (?Popup $record) => PopupBlockRegistry::allowedBlocksFor($record ?? new Popup(['type' => 'simple'])))
                        ->collapsible()
                        ->cloneable()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make(__('Korting'))
                ->visible(fn (Get $get) => $get('type') === 'discount')
                ->schema([
                    Radio::make('discount_type')
                        ->label(__('Type korting'))
                        ->options([
                            'percentage' => __('Percentage'),
                            'amount' => __('Vast bedrag'),
                        ])
                        ->default('percentage')
                        ->reactive()
                        ->required(),
                    TextInput::make('discount_percentage')
                        ->label(__('Kortingspercentage'))
                        ->helperText(__('Decimalen toegestaan (bijv. 12.5)'))
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->maxValue(99.99)
                        ->default(10)
                        ->required(fn (Get $get) => ($get('discount_type') ?? 'percentage') === 'percentage')
                        ->visible(fn (Get $get) => ($get('discount_type') ?? 'percentage') === 'percentage'),
                    TextInput::make('discount_amount')
                        ->label(__('Kortingsbedrag'))
                        ->prefix('€')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(fn (Get $get) => $get('discount_type') === 'amount')
                        ->visible(fn (Get $get) => $get('discount_type') === 'amount'),
                    TextInput::make('discount_valid_days')
                        ->label(__('Geldig voor (dagen)'))
                        ->numeric()
                        ->minValue(1)
                        ->default(14),
                    TextInput::make('discount_usage_limit')
                        ->label(__('Hoe vaak mag de kortingscode gebruikt worden?'))
                        ->helperText(__('Totaal aantal keer dat de code gebruikt kan worden.'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    Toggle::make('auto_apply_discount')
                        ->label(__('Automatisch toepassen op winkelmand'))
                        ->default(true),
                    Radio::make('minimal_requirements')
                        ->label(__('Minimale eisen'))
                        ->options([
                            'none' => __('Geen'),
                            'products' => __('Minimaal aantal producten'),
                            'amount' => __('Minimaal aankoopbedrag'),
                        ])
                        ->default('none')
                        ->reactive(),
                    TextInput::make('minimum_products_count')
                        ->label(__('Minimum aantal producten'))
                        ->numeric()
                        ->minValue(1)
                        ->required(fn (Get $get) => $get('minimal_requirements') === 'products')
                        ->visible(fn (Get $get) => $get('minimal_requirements') === 'products'),
                    TextInput::make('minimum_amount')
                        ->label(__('Minimum aankoopbedrag'))
                        ->prefix('€')
                        ->numeric()
                        ->minValue(1)
                        ->required(fn (Get $get) => $get('minimal_requirements') === 'amount')
                        ->visible(fn (Get $get) => $get('minimal_requirements') === 'amount'),
                    Radio::make('valid_for')
                        ->label(__('Van toepassing op'))
                        ->options([
                            'all' => __('Alle producten'),
                            'products' => __('Specifieke producten'),
                            'categories' => __('Specifieke categorieën'),
                        ])
                        ->default('all')
                        ->reactive(),
                    Select::make('discount_product_ids')
                        ->label(__('Producten'))
                        ->multiple()
                        ->searchable()
                        ->dehydrated(false)
                        ->options(fn () => \Dashed\DashedEcommerceCore\Models\Product::query()->pluck('name', 'id')->toArray())
                        ->required(fn (Get $get) => $get('valid_for') === 'products')
                        ->visible(fn (Get $get) => $get('valid_for') === 'products'),
                    Select::make('discount_category_ids')
                        ->label(__('Categorieën'))
                        ->multiple()
                        ->searchable()
                        ->dehydrated(false)
                        ->options(fn () => \Dashed\DashedEcommerceCore\Models\ProductCategory::all()->pluck('nameWithParents', 'id')->toArray())
                        ->required(fn (Get $get) => $get('valid_for') === 'categories')
                        ->visible(fn (Get $get) => $get('valid_for') === 'categories'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            Section::make(__('Trigger'))
                ->schema([
                    Select::make('trigger_type')
                        ->label(__('Wanneer tonen'))
                        ->options([
                            'delay' => __('Tijdsvertraging'),
                            'scroll' => __('Scroll-diepte'),
                            'exit_intent' => __('Exit-intent'),
                        ])
                        ->default('delay')
                        ->live()
                        ->required(),
                    TextInput::make('trigger_value')
                        ->numeric()
                        ->default(5)
                        ->label(fn (Get $get) => match ($get('trigger_type')) {
                            'scroll' => __('Scroll %'),
                            default => __('Seconden'),
                        })
                        ->visible(fn (Get $get) => in_array($get('trigger_type'), ['delay', 'scroll'], true)),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make(__('Display'))
                ->schema([
                    Toggle::make('active')
                        ->label(__('Actief'))
                        ->default(false),
                    Toggle::make('notify_on_conversion')
                        ->label(__('Stuur Telegram-notificatie bij conversie'))
                        ->helperText(__('Gebruikt de algemene Telegram-bot uit instellingen.'))
                        ->default(false),
                    DateTimePicker::make('start_date')
                        ->label(__('Start datum'))
                        ->default(now())
                        ->required(),
                    DateTimePicker::make('end_date')
                        ->label(__('Eind datum'))
                        ->default(now()->addYear())
                        ->required(),
                    TextInput::make('show_again_after')
                        ->label(__('Opnieuw tonen na (minuten)'))
                        ->helperText(__('20160 = 14 dagen'))
                        ->default(20160)
                        ->required()
                        ->numeric(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make(__('Nieuwsbrief koppeling'))
                ->description(__('Stuur ingevulde e-mailadressen automatisch door naar nieuwsbrief-lijsten.'))
                ->visible(count(forms()->builder('popupApiClasses')) > 0)
                ->schema(function () {
                    $apiFields = [];
                    foreach (forms()->builder('popupApiClasses') as $api) {
                        foreach ($api['class']::formFields() as $field) {
                            $apiFields[] = $field
                                ->visible(fn (Get $get) => $get('class') == $api['class']);
                        }
                    }

                    return [
                        Repeater::make('api_subscriptions')
                            ->label(__('Koppelingen'))
                            ->reactive()
                            ->schema(array_merge([
                                Select::make('class')
                                    ->label(__('Nieuwsbriefdienst'))
                                    ->options(collect(forms()->builder('popupApiClasses'))->pluck('name', 'class')->toArray())
                                    ->required()
                                    ->reactive(),
                            ], $apiFields))
                            ->addActionLabel(__('Koppeling toevoegen'))
                            ->columns(['default' => 1, 'lg' => 2])
                            ->columnSpanFull(),
                    ];
                })
                ->columnSpanFull(),

            Section::make(__('Follow-up flow'))
                ->description(__('Stuur automatisch een reeks follow-up mails naar gebruikers die hun email hebben ingevuld maar (nog) niet hebben besteld. Stopt automatisch zodra een betaalde order met dit emailadres binnenkomt.'))
                ->schema([
                    Select::make('follow_up_flow_id')
                        ->label(__('Flow'))
                        ->options(\Dashed\DashedPopups\Models\PopupFollowUpFlow::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray())
                        ->placeholder(__('- Standaard flow gebruiken (indien ingesteld) -'))
                        ->helperText(__('Kies een specifieke flow voor deze popup. Leeg laten = de globaal als standaard gemarkeerde actieve flow wordt gebruikt; is er geen actieve standaard, dan worden er geen follow-ups verstuurd.'))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make(__('Weergave'))
                ->schema([
                    Radio::make('visibility_mode')
                        ->label(__('Waar tonen?'))
                        ->options([
                            'everywhere' => __('Overal'),
                            'only_selection' => __('Alleen op de selectie hieronder'),
                        ])
                        ->default('everywhere')
                        ->required()
                        ->live()
                        ->columnSpanFull(),

                    Section::make(__('Tonen op'))
                        ->visible(fn (Get $get) => $get('visibility_mode') === 'only_selection')
                        ->schema([
                            Repeater::make('include_url_patterns')
                                ->label(__('URL-patronen'))
                                ->helperText(__('Bijvoorbeeld /shop/*, /checkout'))
                                ->simple(
                                    TextInput::make('pattern')
                                        ->placeholder(__('/shop/*'))
                                        ->required()
                                )
                                ->dehydrated(false)
                                ->columnSpanFull(),

                            static::modelTargetingFieldset('include'),
                        ])
                        ->columnSpanFull(),

                    Section::make(__('Uitsluiten op'))
                        ->schema([
                            Repeater::make('exclude_url_patterns')
                                ->label(__('URL-patronen'))
                                ->helperText(__('Deze winnen altijd van include-regels'))
                                ->simple(
                                    TextInput::make('pattern')
                                        ->placeholder(__('/checkout'))
                                        ->required()
                                )
                                ->dehydrated(false)
                                ->columnSpanFull(),

                            static::modelTargetingFieldset('exclude'),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make(__('Aanbevelingen'))
                ->description(__('Toon AI-aanbevolen producten in deze popup.'))
                ->columnSpanFull()
                ->schema([
                    Select::make('recommendation_strategy_slug')
                        ->label(__('Aanbevelingen-strategie'))
                        ->helperText(__('Laat leeg om geen aanbevelingen te tonen.'))
                        ->options(function () {
                            if (! class_exists(\Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationRegistry::class)) {
                                return [];
                            }
                            $entries = app(\Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationRegistry::class)->all();

                            return collect($entries)
                                ->mapWithKeys(function ($entry) {
                                    $slug = method_exists($entry, 'key') ? $entry->key() : (string) $entry;

                                    return [$slug => str_replace('_', ' ', ucfirst($slug))];
                                })
                                ->all();
                        })
                        ->nullable()
                        // Virtual field: persisted to dashed__popup_targets via
                        // syncPopupTargets(), NOT a column on dashed__popups.
                        // Must stay dehydrated(false) like the other target fields,
                        // otherwise Filament writes it to the popups row and the
                        // save fails with "Unknown column recommendation_strategy_slug".
                        ->dehydrated(false)
                        ->placeholder(__('Geen aanbevelingen'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('active')
                    ->label(__('Actief'))
                    ->boolean(),
                TextColumn::make('cached_views_count')
                    ->label(__('Impressies'))
                    ->sortable(),
                TextColumn::make('cached_submits_count')
                    ->label(__('Submits'))
                    ->sortable(),
                TextColumn::make('cached_in_flow_count')
                    ->label(__('In flow'))
                    ->sortable(),
                TextColumn::make('conversion')
                    ->label(__('Conversie'))
                    ->getStateUsing(function ($record) {
                        $views = (int) ($record->cached_views_count ?? 0);
                        $submits = (int) ($record->cached_submits_count ?? 0);

                        return $views > 0 ? round(($submits / $views) * 100, 1).'%' : '-';
                    }),
                TextColumn::make('cached_dismissals_count')
                    ->label(__('Wegklik'))
                    ->sortable(),
                TextColumn::make('dismissal_rate')
                    ->label(__('Wegklik %'))
                    ->getStateUsing(function ($record) {
                        $views = (int) ($record->cached_views_count ?? 0);
                        $dismissals = (int) ($record->cached_dismissals_count ?? 0);

                        return $views > 0 ? round(($dismissals / $views) * 100, 1).'%' : '-';
                    }),
                TextColumn::make('overall_status_30d')
                    ->label(__('Status (30d)'))
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
                    ->label(__('Bounce (30d)'))
                    ->getStateUsing(function ($record) {
                        $views = (int) ($record->cached_views_30d ?? 0);
                        $bounces = (int) ($record->cached_bounces_30d ?? 0);

                        return $views > 0 ? number_format(($bounces / $views) * 100, 1).'%' : '-';
                    }),
                TextColumn::make('cached_revenue_30d')
                    ->label(__('Omzet (30d)'))
                    ->alignment('right')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0 ? CurrencyHelper::formatPrice((float) $state) : '-'),
                static::lastEditedColumn(),
            ])
            ->modifyQueryUsing(fn ($query) => static::modifyTableQueryForLastEdited($query))
            ->recordActions([
                EditAction::make()->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions(ToolbarActions::getActions())
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Actief'))
                    ->placeholder(__('Alle popups'))
                    ->trueLabel('Alleen actieve')
                    ->falseLabel('Alleen inactieve')
                    ->queries(
                        true: fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('active', true),
                        false: fn (\Illuminate\Database\Eloquent\Builder $q) => $q->where('active', false),
                        blank: fn (\Illuminate\Database\Eloquent\Builder $q) => $q,
                    ),
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
            $fields[] = Radio::make("target_mode_{$ruleType}_{$key}")
                ->label(__('Zichtbaar op :model:', ['model' => $label]))
                ->options([
                    'none' => __('Geen beperking'),
                    'all' => __('Alle :model:', ['model' => $label]),
                    'selected' => __('Geselecteerde items'),
                ])
                ->default('none')
                ->live()
                ->dehydrated(false)
                ->columnSpanFull();

            $fields[] = Select::make("target_ids_{$ruleType}_{$key}")
                ->label(__('Selecteer :model:', ['model' => $label]))
                ->multiple()
                ->searchable()
                ->options(fn () => $modelClass::query()->limit(200)->pluck('name', 'id')->all())
                ->visible(fn (Get $get) => $get("target_mode_{$ruleType}_{$key}") === 'selected')
                ->dehydrated(false)
                ->columnSpanFull();
        }

        return Fieldset::make(__('Per modeltype'))
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
