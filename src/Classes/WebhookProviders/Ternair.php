<?php

namespace Dashed\DashedForms\Classes\WebhookProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedForms\Models\FormInput;

class Ternair
{
    public $name = 'Ternair';
    public $slug = 'ternair';

    public static function dispatch(FormInput $formInput)
    {
        $data = [];
        $data['ip'] = $formInput->ip;
        $data['user_agent'] = $formInput->user_agent;
        $data['from_url'] = $formInput->from_url;
        $data['site_id'] = $formInput->site_id;
        $data['locale'] = $formInput->locale;
        $data['created_at'] = $formInput->created_at;

        foreach ($formInput->formFields as $field) {
            $data['data'][$field->formField->name] = $field->formField->type == 'file' ? Storage::disk('dashed')->url($field->value) : $field->value;
            //            $data[$field->formField->name] = $field->formField->type == 'file' ? Storage::disk('dashed')->url($field->value) : $field->value;
        }

        foreach (str(str($formInput->from_url)->explode('?')->last())->explode('&') as $query) {
            $query = str($query)->explode('=');
            $data[$query[0]] = $query[1] ?? '';
            $data['queryParams'][$query[0]] = $query[1] ?? '';
        }

        $response = Http::post($formInput->form->webhook_url, $data);

        if ($response->failed()) {
            $formInput->webhook_error = $response->body();
        }

        $formInput->webhook_send = $response->successful() ? 1 : 2;
        $formInput->save();
    }
}
