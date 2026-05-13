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
                        ->helperText('Decimalen toegestaan (bijv. 12.5)')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->maxValue(99.99)
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

            Section::make('Nieuwsbrief koppeling')
                ->description('Stuur ingevulde e-mailadressen automatisch door naar nieuwsbrief-lijsten.')
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
                            ->label('Koppelingen')
                            ->reactive()
                            ->schema(array_merge([
                                Select::make('class')
                                    ->label('Nieuwsbriefdienst')
                                    ->options(collect(forms()->builder('popupApiClasses'))->pluck('name', 'class')->toArray())
                                    ->required()
                                    ->reactive(),
                            ], $apiFields))
                            ->addActionLabel('Koppeling toevoegen')
                            ->columns(['default' => 1, 'lg' => 2])
                            ->columnSpanFull(),
                    ];
                })
                ->columnSpanFull(),

            Section::make('Follow-up flow')
                ->description('Stuur automatisch een reeks follow-up mails naar gebruikers die hun email hebben ingevuld maar (nog) niet hebben besteld. Stopt automatisch zodra een betaalde order met dit emailadres binnenkomt.')
                ->schema([
                    Select::make('follow_up_flow_id')
                        ->label('Flow')
                        ->options(\Dashed\DashedPopups\Models\PopupFollowUpFlow::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->toArray())
                        ->placeholder('- Standaard flow gebruiken (indien ingesteld) -')
                        ->helperText('Kies een specifieke flow voor deze popup. Leeg laten = de globaal als standaard gemarkeerde actieve flow wordt gebruikt; is er geen actieve standaard, dan worden er geen follow-ups verstuurd.')
                        ->columnSpanFull(),
                ])
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

            Section::make('Aanbevelingen')
                ->description('Toon AI-aanbevolen producten in deze popup.')
                ->columnSpanFull()
                ->schema([
                    Select::make('recommendation_strategy_slug')
                        ->label('Aanbevelingen-strategie')
                        ->helperText('Laat leeg om geen aanbevelingen te tonen.')
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
                        ->dehydrated(true)
                        ->placeholder('Geen aanbevelingen')
                        ->columnSpanFull(),
                ]),
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
                TextColumn::make('cached_views_count')
                    ->label('Impressies')
                    ->sortable(),
                TextColumn::make('cached_submits_count')
                    ->label('Submits')
                    ->sortable(),
                TextColumn::make('cached_in_flow_count')
                    ->label('In flow')
                    ->sortable(),
                TextColumn::make('conversion')
                    ->label('Conversie')
                    ->getStateUsing(function ($record) {
                        $views = (int) ($record->cached_views_count ?? 0);
                        $submits = (int) ($record->cached_submits_count ?? 0);

                        return $views > 0 ? round(($submits / $views) * 100, 1).'%' : '-';
                    }),
                TextColumn::make('cached_dismissals_count')
                    ->label('Wegklik')
                    ->sortable(),
                TextColumn::make('dismissal_rate')
                    ->label('Wegklik %')
                    ->getStateUsing(function ($record) {
                        $views = (int) ($record->cached_views_count ?? 0);
                        $dismissals = (int) ($record->cached_dismissals_count ?? 0);

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
                        $views = (int) ($record->cached_views_30d ?? 0);
                        $bounces = (int) ($record->cached_bounces_30d ?? 0);

                        return $views > 0 ? number_format(($bounces / $views) * 100, 1).'%' : '-';
                    }),
                TextColumn::make('cached_revenue_30d')
                    ->label('Omzet (30d)')
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
                    ->label('Actief')
                    ->placeholder('Alle popups')
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
                    'none' => 'Geen beperking',
                    'all' => __('Alle :model:', ['model' => $label]),
                    'selected' => 'Geselecteerde items',
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
