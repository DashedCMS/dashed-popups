<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedForms\Models\FormInput;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class ViewFormInput extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    protected static string $resource = FormResource::class;

    protected static string $view = 'dashed-forms::forms.pages.view-form-input';

    public $record;

    public function mount($record, FormInput $formInput): void
    {
        $this->record = $formInput;
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $lastBreadcrumb = $breadcrumbs[0];
        array_pop($breadcrumbs);
        $breadcrumbs[route('filament.dashed.resources.forms.viewInputs', [$this->record->form->id])] = "Aanvragen voor {$this->record->form->name}";
        $breadcrumbs[] = $lastBreadcrumb;

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        $actions = [
            Action::make('mark_as_not_viewed')
                ->button()
                ->visible($this->record->viewed)
                ->label('Markeer als niet bekeken')
                ->action('markAsNotViewed'),
            Action::make('mark_as_viewed')
                ->button()
                ->visible(! $this->record->viewed)
                ->label('Markeer als bekeken')
                ->action('markAsViewed'),
            Action::make('delete')
                ->button()
                ->requiresConfirmation()
                ->color('danger')
                ->label('Verwijderen')
                ->action('delete'),
        ];

        return $actions;
    }

    public function markAsNotViewed(): void
    {
        $this->record->viewed = 0;
        $this->record->save();
    }

    public function markAsViewed(): void
    {
        $this->record->viewed = 1;
        $this->record->save();
    }

    public function delete()
    {
        $this->record->delete();

        return redirect()->route('filament.dashed.resources.forms.viewInputs', [$this->record->form->id]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $inputFields = [];
        $inputFields[] = TextEntry::make('')
            ->hiddenLabel()
            ->weight(FontWeight::Bold)
            ->size(TextEntry\TextEntrySize::Large)
            ->state('Ingevoerde informatie');
        if ($this->record->content) {
            foreach ($this->record->content as $key => $value) {
                $inputFields[] = TextEntry::make($key)
                    ->label(Str::of($key)->replace('_', ' ')->title())
                    ->state($value);
            }
        } else {
            foreach ($this->record->formFields as $field) {
                if ($field->isImage()) {
                    if ($field->formField->type == 'select-image') {
                        $inputFields[] = ImageEntry::make($field->formField->id)
                            ->label($field->formField->name)
                            ->helperText(collect($field->formField->images)->where('image', $field->value)->first()['name'])
                            ->state($field->value);
                    } else {
                        if (str($field->value)->contains(['.jpg','.jpeg','.png','.gif','.svg'])) {
                            $inputFields[] = ImageEntry::make($field->formField->id)
                                ->label($field->formField->name)
                                ->url(Storage::disk('dashed')->url($field->value))
                                ->openUrlInNewTab()
                                ->helperText('Klik de afbeelding om te openen')
                                ->state($field->value);
                        } else {
                            $inputFields[] = TextEntry::make($field->formField->id)
                                ->label(Str::of($field->value)->lower()->replace(' ', '_'))
                                ->url(Storage::disk('dashed')->url($field->value))
                                ->openUrlInNewTab()
                                ->state('Download bestand');
                        }
                    }
                } else {
                    $inputFields[] = TextEntry::make($field->formField->id)
                        ->label($field->formField->name)
                        ->state($field->value)
                        ->prose();
                }
            }
        }

        $inputFields[] =
            TextEntry::make('viewed')
                ->label('Bekeken')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    '1' => 'Ja',
                    '0' => 'Nee',
                })
                ->color(fn (string $state): string => match ($state) {
                    '1' => 'success',
                    '0' => 'danger',
                });

        return $infolist
            ->record($this->record)
            ->schema([
                Split::make([
                    Section::make($inputFields)
                        ->grow(),
                    Section::make([
                        TextEntry::make('')
                            ->hiddenLabel()
                            ->weight(FontWeight::Bold)
                            ->size(TextEntry\TextEntrySize::Large)
                            ->state('Overige informatie'),
                        TextEntry::make('ip')
                            ->label('IP')
                            ->default('Onbekend'),
                        TextEntry::make('user_agent')
                            ->label('User agent')
                            ->default('Onbekend'),
                        TextEntry::make('from_url')
                            ->label('Ingevoerd vanaf')
                            ->url($this->record->from_url)
                            ->openUrlInNewTab()
                            ->default('Onbekend'),
                        TextEntry::make('created_at')
                            ->label('Ingevoerd op')
                            ->default('Onbekend'),
                        TextEntry::make('site_id')
                            ->label('Site ID')
                            ->visible(count(Sites::getSites()) > 1)
                            ->default('Onbekend'),
                        TextEntry::make('locale')
                            ->label('Taal')
                            ->visible(count(Locales::getLocales()) > 1)
                            ->default('Onbekend'),
                    ]),
                ])
                    ->from('md'),
            ]);
    }

    public function getTitle(): string
    {
        return "Aanvraag #{$this->record->id} voor {$this->record->form->name}";
    }
}
