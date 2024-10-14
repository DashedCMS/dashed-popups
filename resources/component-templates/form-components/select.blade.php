<div class="flex flex-col gap-2 tracking-tight">
    <header>
        <h3 class="font-semibold text-lg">
            {{ $field->labelName }}
        </h3>
    </header>
    <select @if($field->required)
                required
            @endif wire:model.live="values.{{$field->fieldName}}"
            id="{{ $field->fieldName }}" name="{{ $field->fieldName }}" class="focus:ring-2 focus:ring-primary transition px-5 py-3.5 border-none rounded-lg bg-sahara-shadow placeholder:text-coal/50">
        @foreach($field->options as $option)
            <option value="{{ $option['name'] }}">{{ $option['name'] }}</option>
        @endforeach
    </select>
    @if($field->helper_text)
        <span class="text-neutral text-sm">{{ $field->helper_text }}</span>
    @endif
    @error('values.' . $field->fieldName) <span class="text-red-500 font-bold">{{ $message }}</span> @enderror
</div>
