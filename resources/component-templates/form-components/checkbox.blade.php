<div class="flex flex-col gap-2 tracking-tight">
    <header>
        <h3 class="font-semibold text-lg">
            {{ $field->labelName }}
        </h3>
    </header>
    @foreach($field->options as $option)
        <div class="focus:ring-2 focus:ring-primary transition border-none rounded-lg placeholder:text-coal/50 flex gap-2 items-center">
            <input wire:model="values.{{$field->fieldName}}.{{ str($option['name'])->slug() }}" type="checkbox"
                   id="{{ str($option['name'])->slug() }}" name="{{ $field->fieldName }}"
                   value="{{ $option['name'] }}">
            <label for="{{ str($option['name'])->slug() }}">{{ $option['name'] }}</label>
        </div>
    @endforeach
    @if($field->helper_text)
        <span class="text-neutral text-sm">{{ $field->helper_text }}</span>
    @endif
    @error('values.' . $field->fieldName) <span class="text-red-500 font-bold">{{ $message }}</span> @enderror
</div>
