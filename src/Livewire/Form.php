<?php

namespace Dashed\DashedForms\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\App;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedForms\Models\FormField;
use Dashed\DashedForms\Models\FormInput;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedForms\Enums\MailingProviders;
use Dashed\DashedForms\Mail\CustomFormSubmitConfirmationMail;
use Dashed\DashedForms\Mail\AdminCustomFormSubmitConfirmationMail;

class Form extends Component
{
    use WithFileUploads;

    public \Dashed\DashedForms\Models\Form $form;
    public array $values = [];
    public array $blockData = [];
    public array $inputData = [];
    public bool $formSent = false;
    public ?string $myName = '';
    public bool $singleColumn = false;
    public ?string $buttonTitle = '';

    protected $listeners = [
        'setValue',
    ];

    public function mount(\Dashed\DashedForms\Models\Form $formId, array $blockData = [], array $inputData = [], bool $singleColumn = false, ?string $buttonTitle = '')
    {
        $this->singleColumn = $singleColumn;
        $this->form = $formId;
        $this->blockData = $blockData;
        $this->inputData = $inputData;
        $this->buttonTitle = $buttonTitle;
        $this->resetForm();
    }

    public function getFormFieldsProperty()
    {
        return $this->form->fields;
    }

    public function resetForm()
    {
        foreach ($this->formFields as $field) {
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

    protected function mapRules(FormField $field): array
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
        return collect($this->formFields)
            ->flatMap(fn (FormField $field) => ['values.' . $field->fieldName => strtolower($field->name)])
            ->toArray();
    }

    protected function rules()
    {
        return collect($this->formFields)
            ->flatMap(fn (FormField $field) => ['values.' . $field->fieldName => $this->mapRules($field)])
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
            $this->addError('values.' . $this->form->fields()->where('type', '!=', 'info')->first()->fieldName, 'Je bent een bot!');

            return Notification::make()
                ->danger()
                ->body('Je bent een bot!')
                ->send();
        }

        $formInput = new FormInput();
        $formInput->form_id = $this->form->id;
        $formInput->ip = request()->ip();
        $formInput->user_agent = request()->userAgent();
        $formInput->from_url = url()->previous();
        $formInput->site_id = Sites::getActive();
        $formInput->locale = App::getLocale();
        $formInput->save();

        foreach ($this->values as $fieldName => $value) {
            $field = FormField::find(str($fieldName)->explode('-')->last());
            if ($field->type == 'checkbox') {
                $value = implode(', ', $value);
                //            } elseif ($field->type == 'file') {
                //                if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                //                    $value = $value->storeAs('dashed', "forms/" . Str::slug($this->form->name) . "/" . time() . '.' . $value->getClientOriginalExtension(), 'dashed');
                //                }else{
                //                    $value = null;
                //                }
            }

            if ($value) {
                $formInput->formFields()->create([
                    'value' => $value,
                    'form_field_id' => $field->id,
                ]);

                if ($formInput->form->emailConfirmationFormField && $field->id == $formInput->form->emailConfirmationFormField->id) {
                    $sendToFieldValue = $value;
                }
            }
        }

        if ($sendToFieldValue ?? false) {
            try {
                Mail::to($sendToFieldValue)->send(new CustomFormSubmitConfirmationMail($formInput));
            } catch (\Exception $e) {
            }
        }

        try {
            $notificationFormInputsEmails = Customsetting::get('notification_form_inputs_emails', Sites::getActive(), '[]');
            if ($notificationFormInputsEmails) {
                foreach (json_decode($notificationFormInputsEmails) as $notificationFormInputsEmail) {
                    Mail::to($notificationFormInputsEmail)->send(new AdminCustomFormSubmitConfirmationMail($formInput, $sendToFieldValue ?? null));
                }
            }
        } catch (\Exception $e) {
        }

        foreach (MailingProviders::cases() as $provider) {
            $provider = $provider->getClass();
            if ($provider->connected) {
                $provider->createContactFromFormInput($formInput);
            }
        }

        $this->resetForm();
        $this->formSent = true;

        $this->dispatch('formSubmitted', [
            'formId' => $this->form->id,
        ]);

        Notification::make()
            ->success()
            ->body('Je bericht is verzonden!')
            ->send();

        $redirectUrl = $this->form->redirect_after_form ? linkHelper()->getUrl($this->form->redirect_after_form) : '';
        if ($redirectUrl) {
            return redirect($redirectUrl);
        }
    }

    public function updated($name, $value)
    {
        if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $path = $value->storeAs('dashed', "forms/form-{$this->form->name}-" . time() . '.' . $value->getClientOriginalExtension(), 'dashed');
            $this->values[str($name)->explode('.')->last()] = $path;
        }
    }

    public function setValueForField(string $field, string $value)
    {
        $this->values[$field] = $value;
    }

    public function render()
    {
        if (view()->exists('dashed.forms.' . str($this->form->name)->slug() . '-form')) {
            return view(env('SITE_THEME', 'dashed') . '.forms.' . str($this->form->name)->slug() . '-form');
        } else {
            return view(env('SITE_THEME', 'dashed') . '.forms.form');
        }
    }
}
