<?php

namespace Dashed\DashedPopups\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Mail;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Dashed\DashedPopups\Models\PopupView;
use Filament\Forms\Components\RichEditor;
use Dashed\DashedPopups\Mail\PopupFollowUpMail;
use Dashed\DashedPopups\Models\PopupFollowUpFlow;
use Dashed\DashedPopups\Models\PopupFollowUpEmail;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages\EditPopupFollowUpFlow;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages\ListPopupFollowUpFlows;
use Dashed\DashedPopups\Filament\Resources\PopupFollowUpFlowResource\Pages\CreatePopupFollowUpFlow;

class PopupFollowUpFlowResource extends Resource
{
    public const VARIABLES_HELP = 'Variabelen: :siteName: :email: :discountCode: :discountValue: :siteUrl:';

    protected static ?string $model = PopupFollowUpFlow::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Communicatie';

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
            Section::make(__('Algemeen'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Naam'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label(__('Actieve flow'))
                        ->default(true)
                        ->helperText(__('Slechts één flow kan actief zijn tegelijk. Een nieuwe actieve flow zet de vorige automatisch op inactive.')),
                    Toggle::make('is_default')
                        ->label(__('Standaard flow'))
                        ->helperText(__('De standaard flow wordt gebruikt voor popups die zelf geen flow hebben gekozen. Slechts één flow tegelijk kan standaard zijn.')),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make(__('Opvolg-emails'))
                ->description(__('Voeg de emails toe die in volgorde verstuurd worden nadat een bezoeker zijn email achterlaat zonder af te rekenen.'))
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
                        ->addActionLabel(__('Email toevoegen'))
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->extraItemActions([
                            Action::make('sendTestMail')
                                ->label(__('Test mail naar mij sturen'))
                                ->icon('heroicon-o-paper-airplane')
                                ->color('info')
                                ->modalHeading(__('Test mail versturen'))
                                ->modalDescription(__('Verstuurt een synchrone test-render van deze mail naar het opgegeven adres. Werkt ook voor nog niet opgeslagen wijzigingen.'))
                                ->modalSubmitActionLabel(__('Versturen'))
                                ->form([
                                    TextInput::make('recipient')
                                        ->label(__('Ontvanger'))
                                        ->email()
                                        ->required()
                                        ->default(fn () => auth()->user()?->email),
                                ])
                                ->action(function (array $arguments, array $data, Repeater $component): void {
                                    $itemState = $component->getRawItemState($arguments['item']);
                                    $locale = app()->getLocale();
                                    $recipient = (string) ($data['recipient'] ?? '');

                                    $blocks = $itemState['blocks'] ?? [];
                                    if (! is_array($blocks)) {
                                        $blocks = [];
                                    }
                                    $blocks = array_values($blocks);

                                    $email = new PopupFollowUpEmail();
                                    $email->send_after_minutes = (int) ($itemState['send_after_minutes'] ?? 60);
                                    $email->is_active = (bool) ($itemState['is_active'] ?? true);
                                    $email->setTranslation('subject', $locale, (string) ($itemState['subject'] ?? 'Test mail'));
                                    $email->setTranslation('blocks', $locale, $blocks);

                                    $popupView = new PopupView();
                                    $popupView->email = $recipient;
                                    $popupView->locale = $locale;

                                    $mailable = new PopupFollowUpMail($popupView, $email, $locale);
                                    $mailable->previewDiscountCode = 'PREVIEW-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                                    $mailable->previewDiscountValue = '10%';

                                    try {
                                        Mail::to($recipient)->sendNow($mailable);

                                        Notification::make()
                                            ->title(__('Test mail verstuurd naar :email', ['email' => $recipient]))
                                            ->success()
                                            ->send();
                                    } catch (\Throwable $e) {
                                        report($e);
                                        Notification::make()
                                            ->title(__('Test mail mislukt'))
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                }),
                        ])
                        ->itemLabel(function (array $state): ?string {
                            $minutes = (int) ($state['send_after_minutes'] ?? 0);
                            $label = static::formatDelayLabel($minutes);
                            $subject = $state['subject'] ?? null;
                            if (is_array($subject)) {
                                $subject = $subject[app()->getLocale()] ?? reset($subject) ?: null;
                            }
                            $subject = is_string($subject) ? trim($subject) : '';

                            return trim($label.($subject !== '' ? ' - '.$subject : ''));
                        })
                        ->schema([
                            TextInput::make('send_after_minutes')
                                ->label(__('Versturen na (minuten)'))
                                ->helperText(__('60 = 1 uur, 1440 = 1 dag, 4320 = 3 dagen'))
                                ->numeric()
                                ->minValue(1)
                                ->default(60)
                                ->required(),
                            Toggle::make('is_active')
                                ->label(__('Actief'))
                                ->default(true),
                            TextInput::make('subject')
                                ->label(__('Onderwerp'))
                                ->helperText(__('Beschikbare :variabelen', ['variabelen' => __(self::VARIABLES_HELP)]))
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Builder::make('blocks')
                                ->label(__('Inhoud blokken'))
                                ->helperText(__(':variabelen - werken in elk tekst-, link- en code-veld hieronder.', ['variabelen' => __(self::VARIABLES_HELP)]))
                                ->blocks([
                                    Builder\Block::make('heading')
                                        ->label(__('Kop'))
                                        ->icon('heroicon-o-bars-3-bottom-left')
                                        ->schema([
                                            TextInput::make('content')
                                                ->label(__('Tekst'))
                                                ->helperText(__(self::VARIABLES_HELP))
                                                ->required(),
                                        ]),
                                    Builder\Block::make('paragraph')
                                        ->label(__('Tekst'))
                                        ->icon('heroicon-o-document-text')
                                        ->schema([
                                            RichEditor::make('content')
                                                ->label(__('Tekst'))
                                                ->helperText(__(self::VARIABLES_HELP))
                                                ->toolbarButtons([
                                                    'bold', 'italic', 'underline', 'strike',
                                                    'link', 'bulletList', 'orderedList', 'h2', 'h3',
                                                ]),
                                        ]),
                                    Builder\Block::make('button')
                                        ->label(__('Knop'))
                                        ->icon('heroicon-o-cursor-arrow-rays')
                                        ->schema([
                                            TextInput::make('label')
                                                ->label(__('Knoptekst'))
                                                ->helperText(__(self::VARIABLES_HELP))
                                                ->default('Bekijk')
                                                ->required(),
                                            TextInput::make('url')
                                                ->label(__('URL'))
                                                ->helperText(__(':variabelen - laat `:siteUrl:` staan voor de homepage.', ['variabelen' => __(self::VARIABLES_HELP)]))
                                                ->default(':siteUrl:')
                                                ->required(),
                                        ]),
                                    Builder\Block::make('image')
                                        ->label(__('Afbeelding'))
                                        ->icon('heroicon-o-photo')
                                        ->schema([
                                            TextInput::make('url')
                                                ->label(__('URL'))
                                                ->helperText(__(self::VARIABLES_HELP))
                                                ->required(),
                                            TextInput::make('alt')
                                                ->label(__('Alt-tekst'))
                                                ->helperText(__(self::VARIABLES_HELP)),
                                        ]),
                                    Builder\Block::make('divider')
                                        ->label(__('Scheidingslijn'))
                                        ->icon('heroicon-o-minus')
                                        ->schema([]),
                                    Builder\Block::make('usp')
                                        ->label(__('USPs'))
                                        ->icon('heroicon-o-check-badge')
                                        ->maxItems(1)
                                        ->schema([
                                            Textarea::make('items')
                                                ->label(__('USPs (één per regel)'))
                                                ->helperText(__('Voer elke USP op een nieuwe regel in. :variabelen', ['variabelen' => __(self::VARIABLES_HELP)]))
                                                ->rows(4)
                                                ->default("Gratis verzending\nSnel geleverd\nVeilig betalen"),
                                        ]),
                                    Builder\Block::make('discount')
                                        ->label(__('Kortingscode'))
                                        ->icon('heroicon-o-tag')
                                        ->maxItems(1)
                                        ->schema([
                                            TextInput::make('label')
                                                ->label(__('Tekst boven de code'))
                                                ->helperText(__(self::VARIABLES_HELP))
                                                ->default('Gebruik deze code voor extra korting:'),
                                            TextInput::make('code')
                                                ->label(__('Code'))
                                                ->helperText(__('Laat leeg om de code van de popup-conversie zelf te gebruiken. Optionele :variabelen', ['variabelen' => __(self::VARIABLES_HELP)])),
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
                    ->label(__('Naam'))
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('emails_count')
                    ->label(__('Emails'))
                    ->counts('emails')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_active')
                    ->label(__('Actief'))
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('is_default')
                    ->label(__('Standaard'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('Bijgewerkt'))
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
