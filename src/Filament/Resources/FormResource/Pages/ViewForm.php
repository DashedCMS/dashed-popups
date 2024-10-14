<?php

namespace Dashed\DashedForms\Filament\Resources\FormResource\Pages;

use Illuminate\Support\Str;
use Filament\Resources\Pages\Page;
use Filament\Tables\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Dashed\DashedForms\Models\FormInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedForms\Exports\ExportFormData;
use Filament\Tables\Concerns\InteractsWithTable;
use Dashed\DashedForms\Filament\Resources\FormResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

class ViewForm extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = FormResource::class;
    protected static string $view = 'dashed-forms::forms.pages.view-form';

    public function getTableSortColumn(): ?string
    {
        return 'viewed';
    }

    public function mount($record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function getTableQuery(): Builder
    {
        return $this->record->inputs()->getQuery();
    }

    public function getTitle(): string
    {
        return "Aanvragen voor {$this->record->name}";
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('viewed')
                ->label('Bekeken')
                ->options([
                    '0' => 'Niet bekeken',
                    '1' => 'Bekeken',
                ]),
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'viewed';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'ASC';
    }

    protected function getTableColumns(): array
    {
        $tableColumns = [
            TextColumn::make('id')
                ->label('ID')
                ->sortable(),
        ];

        $formInput = $this->record->inputs()->first();
        $inputCount = 0;
        if ($formInput->content ?? []) {
            foreach ($formInput->content as $key => $item) {
                if ($inputCount < 4) {
                    $tableColumns[] = TextColumn::make($key)
                        ->label(Str::of($key)->replace('_', ' ')->title())
                        ->getStateUsing(fn ($record) => $record->content[$key] ?? 'Niet ingevuld');
                }
                $inputCount++;
            }
        } else {
            foreach ($this->record->fields()->whereNotIn('type', ['info'])->get() as $item) {
                if ($inputCount < 4) {
                    if ($item->isImage()) {
                        $tableColumns[] = ImageColumn::make($item->name)
                            ->label($item->name)
                            ->getStateUsing(fn ($record) => $record->formFields()->where('form_field_id', $item->id)->first()->value ?? 'Niet ingevuld');
                    } else {
                        $tableColumns[] = TextColumn::make($item->name)
                            ->label($item->name)
                            ->getStateUsing(fn ($record) => $record->formFields()->where('form_field_id', $item->id)->first()->value ?? 'Niet ingevuld');
                    }
                }
                $inputCount++;
            }
        }

        $tableColumns[] =
            IconColumn::make('viewed')
                ->falseIcon('heroicon-o-eye-slash')
                ->trueIcon('heroicon-o-eye')
                ->label('Bekeken')
                ->searchable([
                    'ip',
                    'user_agent',
                    'content',
                    'from_url',
                    'site_id',
                    'locale',
                ])
                ->sortable();

        return $tableColumns;
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('Bekijk')
                ->url(fn (FormInput $record): string => route('filament.dashed.resources.forms.viewInput', [$record->form->id, $record]))
                ->button(),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('delete')
                ->label('Verwijderen')
                ->action(function (Collection $records) {
                    foreach ($records as $record) {
                        $record->delete();
                    }

                    Notification::make()
                        ->success()
                        ->body('Resultaten verwijderd')
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('export')
                ->label('Exporteer')
                ->action(function (Collection $records) {
                    Notification::make()
                        ->success()
                        ->body('Resultaten geëxporteerd')
                        ->send();

                    return Excel::download(new ExportFormData($records), 'form-data.xlsx');
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }
}
