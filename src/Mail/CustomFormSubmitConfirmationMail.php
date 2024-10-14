<?php

namespace Dashed\DashedForms\Mail;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedForms\Models\FormInput;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;

class CustomFormSubmitConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public FormInput $formInput;
    public string $replyToEmail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(FormInput $formInput, string $replyToEmail = '')
    {
        $this->formInput = $formInput;
        $this->replyToEmail = $replyToEmail;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view(env('SITE_THEME', 'dashed') . '.emails.custom-confirm-form-submit')
            ->from(Customsetting::get('site_from_email'), Customsetting::get('company_name'))->subject(Translation::get('form-confirmation-'.Str::slug($this->formInput->form->name).'-email-subject', 'forms', 'We received your form submit!'))
            ->with([
                'formInput' => $this->formInput,
            ]);
    }
}
