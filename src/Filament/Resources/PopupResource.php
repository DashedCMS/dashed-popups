<?php

namespace Dashed\DashedPopups\Filament\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\EditPopup;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\ListPopups;
use Dashed\DashedPopups\Filament\Resources\PopupResource\Pages\CreatePopup;

class PopupResource extends Resource
{
    //    use Translatable;

    protected static ?string $model = \Dashed\DashedPopups\Models\Popup::class;
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $label = 'Popup';
    protected static ?string $pluralLabel = 'Popups';
    protected static bool $isGloballySearchable = false;

    public static function getNavigationLabel(): string
    {
        return 'Popups';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Naam')
                    ->maxLength(255)
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('start_date')
                    ->label('Start datum')
                    ->default(now())
                    ->required(),
                DateTimePicker::make('end_date')
                    ->label('Eind datum')
                    ->default(now()->addYear())
                    ->required(),
                TextInput::make('delay')
                    ->label('Vertraging')
                    ->helperText('Na hoeveel seconden moet de popup getoond worden? 0 = direct')
                    ->default(0)
                    ->required()
                    ->numeric(),
                TextInput::make('show_again_after')
                    ->label('Opnieuw tonen na')
                    ->helperText('Na hoeveel seconden moet de popup opnieuw getoond worden? 1440 = 1 dag')
                    ->default(60 * 24)
                    ->required()
                    ->numeric(),
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
            ])
            ->actions([
                EditAction::make()
                    ->button(),
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
            'index' => ListPopups::route('/'),
            'create' => CreatePopup::route('/create'),
            'edit' => EditPopup::route('/{record}/edit'),
        ];
    }
}
