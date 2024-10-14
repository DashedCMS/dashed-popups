<div class="md:col-span-2 flex flex-col gap-2">
    <label for="{{ $field->fieldName }}" class="font-semibold">{{ $field->labelName }}</label>
    <textarea class="form-input" name="{{ $field->fieldName }}"
              id="{{ $field->fieldName }}"
              @if($field->required) required @endif
              rows="7"
              wire:model.live="values.{{ $field->fieldName }}"
              placeholder="{{ $field->placeholderName }}"></textarea>
    @if($field->helper_text)
        <span class="text-neutral text-sm">{{ $field->helper_text }}</span>
    @endif
    @error('values.' . $field->fieldName) <span class="text-red-500 font-bold">{{ $message }}</span> @enderror
</div>
