<?php

namespace Dashed\DashedPopups\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\App;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedPopups\Models\PopupField;
use Dashed\DashedPopups\Models\PopupInput;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedPopups\Enums\MailingProviders;
use Dashed\DashedPopups\Mail\CustomPopupSubmitConfirmationMail;
use Dashed\DashedPopups\Mail\AdminCustomPopupSubmitConfirmationMail;

class Popup extends Component
{
    use WithFileUploads;

    public \Dashed\DashedPopups\Models\Popup $popup;
    public array $values = [];
    public array $blockData = [];
    public array $inputData = [];
    public bool $popupSent = false;
    public ?string $myName = '';
    public bool $singleColumn = false;
    public ?string $buttonTitle = '';

    protected $listeners = [
        'setValue',
    ];

    public function mount(\Dashed\DashedPopups\Models\Popup $popupId, array $blockData = [], array $inputData = [], bool $singleColumn = false, ?string $buttonTitle = '')
    {
        $this->singleColumn = $singleColumn;
        $this->popup = $popupId;
        $this->blockData = $blockData;
        $this->inputData = $inputData;
        $this->buttonTitle = $buttonTitle;
        $this->resetPopup();
    }

    public function getPopupFieldsProperty()
    {
        return $this->popup->fields;
    }

    public function resetPopup()
    {
        foreach ($this->popupFields as $field) {
            match ($field->type) {
                'radio' => $field->required ? $this->values[$field->fieldName] = $field->options[0]['name'] : null,
                'select' => $this->values[$field->fieldName] = $field->options[0]['name'],
                'select-image' => $this->values[$field->fieldName] = $field->images[0]['image'],
                'input' => $this->values[$field->fieldName] = request()->get(str($field->name)->slug(), $this->inputData[(string)str($field->name)->slug()] ?? ''),
                'textarea' => $this->values[$field->fieldName] = request()->get(str($field->name)->slug(), $this->inputData[(string)str($field->name)->slug()] ?? ''),
                'file' => $this->values[$field->fieldName] = '',
                default => null,
            };
        }
    }

    protected function mapRules(PopupField $field): array
    {
        $rules = [
            'nullable',
        ];

        if ($field->required) {
            $rules[] = 'required';
        }

        if ($field->type === 'input') {
            $rules[] = 'max:255';
            $rules[] = 'string';
        }

        if ($field->type === 'textarea') {
            $rules[] = 'max:5000';
            $rules[] = 'string';
        }

        return $rules;
    }

    protected function validationAttributes()
    {
        return collect($this->popupFields)
            ->flatMap(fn (PopupField $field) => ['values.' . $field->fieldName => strtolower($field->name)])
            ->toArray();
    }

    protected function rules()
    {
        return collect($this->popupFields)
            ->flatMap(fn (PopupField $field) => ['values.' . $field->fieldName => $this->mapRules($field)])
            ->toArray();
    }

    public function setValue($field, $value)
    {
        $this->values[$field] = $value;
    }

    public function submit()
    {
        $this->validate();

        if ($this->myName) {
            $this->addError('values.' . $this->popup->fields()->where('type', '!=', 'info')->first()->fieldName, 'Je bent een bot!');

            return Notification::make()
                ->danger()
                ->body('Je bent een bot!')
                ->send();
        }

        $popupInput = new PopupInput();
        $popupInput->popup_id = $this->popup->id;
        $popupInput->ip = request()->ip();
        $popupInput->user_agent = request()->userAgent();
        $popupInput->from_url = url()->previous();
        $popupInput->site_id = Sites::getActive();
        $popupInput->locale = App::getLocale();
        $popupInput->save();

        foreach ($this->values as $fieldName => $value) {
            $field = PopupField::find(str($fieldName)->explode('-')->last());
            if ($field->type == 'checkbox') {
                $value = implode(', ', $value);
                //            } elseif ($field->type == 'file') {
                //                if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                //                    $value = $value->storeAs('dashed', "popups/" . Str::slug($this->popup->name) . "/" . time() . '.' . $value->getClientOriginalExtension(), 'dashed');
                //                }else{
                //                    $value = null;
                //                }
            }

            if ($value) {
                $popupInput->popupFields()->create([
                    'value' => $value,
                    'popup_field_id' => $field->id,
                ]);

                if ($popupInput->popup->emailConfirmationPopupField && $field->id == $popupInput->popup->emailConfirmationPopupField->id) {
                    $sendToFieldValue = $value;
                }
            }
        }

        if ($sendToFieldValue ?? false) {
            try {
                Mail::to($sendToFieldValue)->send(new CustomPopupSubmitConfirmationMail($popupInput));
            } catch (\Exception $e) {
            }
        }

        try {
            $notificationPopupInputsEmails = Customsetting::get('notification_popup_inputs_emails', Sites::getActive(), '[]');
            if ($notificationPopupInputsEmails) {
                foreach (json_decode($notificationPopupInputsEmails) as $notificationPopupInputsEmail) {
                    Mail::to($notificationPopupInputsEmail)->send(new AdminCustomPopupSubmitConfirmationMail($popupInput, $sendToFieldValue ?? null));
                }
            }
        } catch (\Exception $e) {
        }

        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $provider->createContactFromPopupInput($popupInput);
            }
        }

        $this->resetPopup();
        $this->popupSent = true;

        $this->dispatch('popupSubmitted', [
            'popupId' => $this->popup->id,
        ]);

        Notification::make()
            ->success()
            ->body('Je bericht is verzonden!')
            ->send();

        $redirectUrl = $this->popup->redirect_after_popup ? linkHelper()->getUrl($this->popup->redirect_after_popup) : '';
        if ($redirectUrl) {
            return redirect($redirectUrl);
        }
    }

    public function updated($name, $value)
    {
        if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $path = $value->storeAs('dashed', "popups/popup-{$this->popup->name}-" . time() . '.' . $value->getClientOriginalExtension(), 'dashed');
            $this->values[str($name)->explode('.')->last()] = $path;
        }
    }

    public function setValueForField(string $field, string $value)
    {
        $this->values[$field] = $value;
    }

    public function render()
    {
        if (view()->exists('dashed.popups.' . str($this->popup->name)->slug() . '-popup')) {
            return view(env('SITE_THEME', 'dashed') . '.popups.' . str($this->popup->name)->slug() . '-popup');
        } else {
            return view(env('SITE_THEME', 'dashed') . '.popups.popup');
        }
    }
}
