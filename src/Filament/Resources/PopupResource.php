<?php

namespace Dashed\DashedPopups\Filament\Resources;

use Filament\Popups\Popup;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Dashed\DashedPopups\Classes\Popups;
use Filament\Popups\Components\Select;
use Filament\Popups\Components\Toggle;
use Filament\Popups\Components\Repeater;
use Filament\Popups\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedPopups\Models\PopupInput;
use Filament\Popups\Components\TextInput;
use Filament\Popups\Components\FileUpload;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedPopups\Enums\MailingProviders;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedPopups\Classes\WebhookProviders\Ternair;
use Dashed\DashedCore\Filament\Concerns\HasCustomBlocksTab;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\EditPopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\ListPopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\ViewPopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\CreatePopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\ViewPopupInput;

class PopupResource extends Resource
{
    use Translatable;
    use HasCustomBlocksTab;

    protected static ?string $model = \Dashed\DashedPopups\Models\Popup::class;
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Popupulieren';
    protected static ?string $label = 'Popupulier';
    protected static ?string $pluralLabel = 'Popupulieren';
    protected static bool $isGloballySearchable = false;

    public static function getNavigationLabel(): string
    {
        return 'Popupulieren';
    }

    public static function getNavigationBadge(): ?string
    {
        return PopupInput::unviewed()->count();
    }

    public static function popup(Popup $popup): Popup
    {
        $schema = [
            TextInput::make('name')
                ->label('Naam')
                ->maxLength(255)
                ->required(),
            Select::make('email_confirmation_popup_field_id')
                ->label('Email bevestiging veld')
                ->options(fn ($record) => $record ? $record->fields()->where('type', 'input')->where('input_type', 'email')->pluck('name', 'id') : []),
            TextInput::make('webhook_url')
                ->label('Webhook URL')
                ->helperText('Vul hier de URL in waar de webhook naartoe gestuurd moet worden')
                ->reactive(),
            Select::make('webhook_class')
                ->label('Webhook class')
                ->options([
                    Ternair::class => 'Ternair',
                ])
                ->required(fn ($get) => $get('webhook_url'))
                ->visible(fn ($get) => $get('webhook_url'))
                ->reactive(),
            linkHelper()->field('redirect_after_popup', false, 'Redirect na popupulier'),
        ];


        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $schema[] = Toggle::make("external_options.send_to_$provider->slug")
                    ->label('Verstuur naar ' . $provider->name)
                    ->reactive();
                $schema = array_merge($schema, $provider->getPopupSchema());
            }
        }

        $schema = array_merge($schema, static::customBlocksTab(cms()->builder('popupBlocks')));

        $repeaterSchema = [
            TextInput::make('name')
                ->label('Naam')
                ->maxLength(255)
                ->required(),
            Select::make('type')
                ->label('Type veld')
                ->options(Popups::availableInputTypes())
                ->required()
                ->reactive(),
            Select::make('input_type')
                ->label('Input type veld')
                ->options(Popups::availableInputTypesForInput())
                ->required(fn ($get) => in_array($get('type'), ['input']))
                ->reactive()
                ->visible(fn ($get) => in_array($get('type'), ['input'])),
            TextInput::make('placeholder')
                ->label('Placeholder')
                ->maxLength(255)
                ->visible(fn ($get) => in_array($get('type'), ['input', 'textarea'])),
            TextInput::make('helper_text')
                ->label('Helper tekst')
                ->helperText('Zet hier eventueel uitleg neer over dit veld')
                ->maxLength(255),
            Toggle::make('required')
                ->label('Verplicht in te vullen')
                ->visible(fn ($get) => ! in_array($get('type'), ['info', 'image'])),
            Toggle::make('stack_start')
                ->label('Start van de stack'),
            Toggle::make('stack_end')
                ->label('Einde van de stack'),
            Textarea::make('description')
                ->label('Descriptie')
                ->maxLength(500)
                ->required(fn ($get) => in_array($get('type'), ['info']))
                ->visible(fn ($get) => in_array($get('type'), ['info', 'select-image'])),
            Repeater::make('options')
                ->label('Opties')
                ->required(fn ($get) => in_array($get('type'), ['checkbox', 'radio', 'select']))
                ->visible(fn ($get) => in_array($get('type'), ['checkbox', 'radio', 'select']))
                ->reorderable()
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->maxLength(255)
                        ->required(),
                ]),
            Repeater::make('images')
                ->label('Afbeeldingen')
                ->required(fn ($get) => in_array($get('type'), ['select-image']))
                ->visible(fn ($get) => in_array($get('type'), ['select-image']))
                ->reorderable()
                ->schema([
                    TextInput::make('name')
                        ->label('Naam')
                        ->maxLength(255),
                    FileUpload::make('image')
                        ->label('Afbeelding')
                        ->required()
                        ->image()
                        ->directory('dashed/images'),
                ]),
            FileUpload::make('image')
                ->label('Afbeelding')
                ->required(fn ($get) => in_array($get('type'), ['image']))
                ->visible(fn ($get) => in_array($get('type'), ['image']))
                ->image()
                ->directory('dashed/images'),
        ];

        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $repeaterSchema = array_merge($repeaterSchema, $provider->getPopupFieldSchema());
            }
        }

        $schema[] = Repeater::make('fields')
            ->relationship('fields')
            ->label('Velden')
            ->reorderable()
            ->orderColumn()
            ->reorderableWithButtons()
            ->reorderableWithDragAndDrop()
            ->cloneable()
            ->schema($repeaterSchema)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->columnSpan(2);

        return $popup
            ->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->popupatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable()
                    ->searchable(query: SearchQuery::make()),
                TextColumn::make('amount_of_requests')
                    ->label('Aantal aanvragen')
                    ->getStateUsing(fn ($record) => $record->inputs->count()),
                TextColumn::make('amount_of_unviewed_requests')
                    ->label('Aantal openstaande aanvragen')
                    ->getStateUsing(fn ($record) => $record->inputs()->unviewed()->count()),
            ])
            ->actions([
                EditAction::make()
                    ->button(),
                Action::make('viewInputs')
                    ->label('Bekijk aanvragen')
                    ->icon('heroicon-s-eye')
                    ->button()
                    ->color('primary')
                    ->url(fn ($record) => route('filament.dashed.resources.popups.viewInputs', [$record])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPopup::route('/'),
            'create' => CreatePopup::route('/create'),
            'edit' => EditPopup::route('/{record}/edit'),
            'viewInputs' => ViewPopup::route('/{record}/inputs'),
            'viewInput' => ViewPopupInput::route('/{record}/inputs/{popupInput}'),
        ];
    }
}
