<?php

namespace Dashed\DashedForms\Classes;

use Dashed\DashedForms\Models\Form;
use Filament\Forms\Components\Select;

class Forms
{
    public static function getPostUrl()
    {
        return route('dashed.frontend.forms.store');
    }

    public static function availableInputTypes(): array
    {
        $validTypes = [
            'info' => 'Informatie',
            'image' => 'Afbeelding',
            'input' => 'Tekst',
            'textarea' => 'Tekstvak',
            'checkbox' => 'Checkbox',
            'radio' => 'Radio',
            'select' => 'Select',
            'select-image' => 'Selecteer afbeelding',
            'file' => 'Bestand',
        ];

        foreach ($validTypes as $key => $validType) {
            if (! view()->exists('components.form-components.' . $key)) {
                unset($validTypes[$key]);
            }
        }

        return $validTypes;
    }

    public static function availableInputTypesForInput(): array
    {
        return [
            'text' => 'Tekst',
            'email' => 'Email',
            'number' => 'Nummer',
            'date' => 'Datum',
            'dateTime' => 'Datum en tijd',
            'file' => 'Bestand',
        ];
    }

    public static function formSelecter(string $name = 'form', bool $required = true): Select
    {
        return
            Select::make($name)
                ->label('Formulier')
                ->options(function () {
                    $options = [];
                    foreach (Form::all() as $form) {
                        $options[$form->id] = $form->name;
                    }

                    return $options;
                })
                ->required($required);
    }

    public static function createPresetForms(string $presetForm = 'contact')
    {
        if ($presetForm == 'contact') {
            $form = Form::create([
                'name' => 'Contact formulier',
            ]);

            $form->fields()->create([
                'name' => [
                    app()->getLocale() => 'Naam',
                ],
                'type' => 'input',
                'input_type' => 'text',
                'required' => 1,
                'sort' => 1,
                'helper_text' => [],
            ]);

            $emailField = $form->fields()->create([
                'name' => [
                    app()->getLocale() => 'E-mailadres',
                ],
                'type' => 'input',
                'input_type' => 'email',
                'required' => 1,
                'sort' => 2,
                'helper_text' => [],
            ]);

            $form->fields()->create([
                'name' => [
                    app()->getLocale() => 'Bedrijfsnaam',
                ],
                'type' => 'input',
                'input_type' => 'text',
                'required' => 0,
                'sort' => 3,
                'helper_text' => [],
            ]);

            $form->fields()->create([
                'name' => [
                    app()->getLocale() => 'Telefoonnummer',
                ],
                'type' => 'input',
                'input_type' => 'text',
                'required' => 0,
                'sort' => 4,
                'helper_text' => [],
            ]);

            $form->fields()->create([
                'name' => [
                    app()->getLocale() => 'Bericht',
                ],
                'type' => 'textarea',
                'required' => 1,
                'sort' => 5,
                'placeholder' => [
                    app()->getLocale() => 'Waar kunnen we je mee helpen?',
                ],
                'helper_text' => [],
            ]);

            $form->email_confirmation_form_field_id = $emailField->id;
            $form->save();
        }
    }
}
