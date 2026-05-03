<?php

namespace Dashed\DashedPopups\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\RichEditor;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages\EditPopupFollowUpFlow;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages\ListPopupFollowUpFlows;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages\CreatePopupFollowUpFlow;

class PopupFollowUpFlowResource extends Resource
{
    protected static ?string $model = PopupFollowUpFlow::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $label = 'Popup opvolg-flow';

    protected static ?string $pluralLabel = 'Popup opvolg-flows';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return 'Popup opvolg-emails';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Algemeen')
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Actieve flow')
                        ->default(true)
                        ->helperText('Slechts één flow kan actief zijn tegelijk. Een nieuwe actieve flow zet de vorige automatisch op inactive.'),
                    Toggle::make('is_default')
                        ->label('Standaard flow')
                        ->helperText('De standaard flow wordt gebruikt voor popups die zelf geen flow hebben gekozen. Slechts één flow tegelijk kan standaard zijn.'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Opvolg-emails')
                ->description('Voeg de emails toe die in volgorde verstuurd worden nadat een bezoeker zijn email achterlaat zonder af te rekenen.')
                ->schema([
                    Repeater::make('emails')
                        ->relationship()
                        ->mutateRelationshipDataBeforeFillUsing(static function (array $data): array {
                            $locale = app()->getLocale();
                            foreach (['subject', 'blocks'] as $field) {
                                if (! array_key_exists($field, $data)) {
                                    continue;
                                }
                                $value = $data[$field];
                                if (is_array($value) && ! array_is_list($value)) {
                                    $value = $value[$locale] ?? null;
                                }
                                if ($field === 'blocks') {
                                    $data[$field] = is_array($value) ? array_values($value) : [];
                                } else {
                                    $data[$field] = is_string($value) ? $value : '';
                                }
                            }

                            return $data;
                        })
                        ->orderColumn('sort')
                        ->defaultItems(1)
                        ->addActionLabel('Email toevoegen')
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            $minutes = (int) ($state['send_after_minutes'] ?? 0);
                            $label = static::formatDelayLabel($minutes);
                            $subject = $state['subject'] ?? null;
                            if (is_array($subject)) {
                                $subject = $subject[app()->getLocale()] ?? reset($subject) ?: null;
                            }
                            $subject = is_string($subject) ? trim($subject) : '';

                            return trim($label.($subject !== '' ? ' — '.$subject : ''));
                        })
                        ->schema([
                            TextInput::make('send_after_minutes')
                                ->label('Versturen na (minuten)')
                                ->helperText('60 = 1 uur, 1440 = 1 dag, 4320 = 3 dagen')
                                ->numeric()
                                ->minValue(1)
                                ->default(60)
                                ->required(),
                            Toggle::make('is_active')
                                ->label('Actief')
                                ->default(true),
                            TextInput::make('subject')
                                ->label('Onderwerp')
                                ->helperText('Beschikbare variabelen: :siteName: :email:')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Builder::make('blocks')
                                ->label('Inhoud blokken')
                                ->blocks([
                                    Builder\Block::make('heading')
                                        ->label('Kop')
                                        ->icon('heroicon-o-bars-3-bottom-left')
                                        ->schema([
                                            TextInput::make('content')
                                                ->label('Tekst')
                                                ->required(),
                                        ]),
                                    Builder\Block::make('paragraph')
                                        ->label('Tekst')
                                        ->icon('heroicon-o-document-text')
                                        ->schema([
                                            RichEditor::make('content')
                                                ->label('Tekst')
                                                ->helperText('Variabelen: :siteName: :email:')
                                                ->toolbarButtons([
                                                    'bold', 'italic', 'underline', 'strike',
                                                    'link', 'bulletList', 'orderedList', 'h2', 'h3',
                                                ]),
                                        ]),
                                    Builder\Block::make('button')
                                        ->label('Knop')
                                        ->icon('heroicon-o-cursor-arrow-rays')
                                        ->schema([
                                            TextInput::make('label')
                                                ->label('Knoptekst')
                                                ->default('Bekijk')
                                                ->required(),
                                            TextInput::make('url')
                                                ->label('URL')
                                                ->required()
                                                ->url(),
                                        ]),
                                    Builder\Block::make('image')
                                        ->label('Afbeelding')
                                        ->icon('heroicon-o-photo')
                                        ->schema([
                                            TextInput::make('url')
                                                ->label('URL')
                                                ->required(),
                                            TextInput::make('alt')
                                                ->label('Alt-tekst'),
                                        ]),
                                    Builder\Block::make('divider')
                                        ->label('Scheidingslijn')
                                        ->icon('heroicon-o-minus')
                                        ->schema([]),
                                    Builder\Block::make('usp')
                                        ->label('USPs')
                                        ->icon('heroicon-o-check-badge')
                                        ->maxItems(1)
                                        ->schema([
                                            Textarea::make('items')
                                                ->label('USPs (één per regel)')
                                                ->helperText('Voer elke USP op een nieuwe regel in')
                                                ->rows(4)
                                                ->default("Gratis verzending\nSnel geleverd\nVeilig betalen"),
                                        ]),
                                    Builder\Block::make('discount')
                                        ->label('Kortingscode')
                                        ->icon('heroicon-o-tag')
                                        ->maxItems(1)
                                        ->schema([
                                            TextInput::make('label')
                                                ->label('Tekst boven de code')
                                                ->default('Gebruik deze code voor extra korting:'),
                                            TextInput::make('code')
                                                ->label('Code')
                                                ->helperText('Laat leeg om de code van de popup-conversie zelf te gebruiken (indien beschikbaar).'),
                                        ]),
                                ])
                                ->columnSpanFull()
                                ->collapsible()
                                ->reorderableWithButtons(),
                        ])
                        ->columns(2)
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
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('emails_count')
                    ->label('Emails')
                    ->counts('emails')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('is_default')
                    ->label('Standaard')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->dateTime('d-m-Y H:i', 'Europe/Amsterdam')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()->button(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPopupFollowUpFlows::route('/'),
            'create' => CreatePopupFollowUpFlow::route('/create'),
            'edit' => EditPopupFollowUpFlow::route('/{record}/edit'),
        ];
    }

    protected static function formatDelayLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'Direct';
        }
        if ($minutes % 1440 === 0) {
            $days = (int) ($minutes / 1440);

            return $days.' '.($days === 1 ? 'dag' : 'dagen');
        }
        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return $hours.' '.($hours === 1 ? 'uur' : 'uur');
        }

        return $minutes.' minuten';
    }
}
