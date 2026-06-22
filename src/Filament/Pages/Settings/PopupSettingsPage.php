<?php

namespace Dashed\DashedPopups\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class PopupSettingsPage extends Page implements HasSchemas
{
    use HasSettingsPermission;
    use InteractsWithSchemas;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Popups';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        foreach (Sites::getSites() as $site) {
            $formData["popups_minutes_between_{$site['id']}"] = Customsetting::get('popups_minutes_between', $site['id'], 30);
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema([
                    TextInput::make("popups_minutes_between_{$site['id']}")
                        ->label('Minimale tijd tussen popups (minuten)')
                        ->helperText('Een bezoeker krijgt binnen deze tijd niet twee verschillende popups achter elkaar. 0 = geen tussentijd.')
                        ->numeric()
                        ->minValue(0)
                        ->default(30),
                ])
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }

        return $schema->schema([
            Tabs::make('Sites')->tabs($tabs),
        ])->statePath('data');
    }

    public function submit()
    {
        foreach (Sites::getSites() as $site) {
            Customsetting::set('popups_minutes_between', $this->form->getState()["popups_minutes_between_{$site['id']}"], $site['id']);
        }

        Notification::make()
            ->title('De popup-instellingen zijn opgeslagen')
            ->success()
            ->send();

        return redirect(PopupSettingsPage::getUrl());
    }
}
