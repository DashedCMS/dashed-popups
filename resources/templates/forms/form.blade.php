<form class="space-y-8 p-4 bg-primary-800 text-white rounded-lg" wire:submit="submit">
    <input wire:model.live="myName" type="text" name="my_name" id="my_name" class="weird_input"/>
{{--    @if($blockData['title'] ?? false)--}}
{{--        <h2 class="text-2xl font-bold tracking-tight md:text-3xl font-headline">{{ $blockData['title'] }}</h2>--}}
{{--    @endif--}}

    @if($formSent)
        <div class="rounded-md bg-green-50 border-2 border-green-400 p-4 col-span-2">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                         fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-bold text-green-800">
                        {{ Translation::get('form-sent', 'forms', 'Het formulier is verzonden') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        @foreach($this->formFields as $field)
            @if($field->stack_start)
                <div class="grid gap-2">
                    @endif
                    <x-dynamic-component wire:key="field.{{ $loop->index }}"
                                         :component="'form-components.' . $field->type" :field="$field" :loop="$loop"
                                         :values="$values"
                                         :fields="$this->formFields"/>
                    @if($field->stack_end)
                </div>
            @endif
        @endforeach
    </div>

    @if($buttonTitle)
        <div class="mt-auto">
            @endif
            <button type="submit"
                    class="button button--primary-light flex items-center">
                <svg wire:loading wire:target="submit" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>
                    {{ $buttonTitle ?: Translation::get('submit', str($form->name)->slug() . '-form', 'Verstuur') }}
                </span>
            </button>
            @if($buttonTitle)
        </div>
    @endif
</form>
