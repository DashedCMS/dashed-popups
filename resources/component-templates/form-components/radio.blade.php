<div class="flex flex-col gap-2 tracking-tight rounded-lg bg-sahara-shadow px-5 py-3.5">
    <header>
        <h3 class="font-semibold text-lg mb-2">
            {{ $field->labelName }}
        </h3>
    </header>
    @php($parentLoop = $loop)
    @foreach($field->options as $option)
        <div class="focus:ring-2 focus:ring-primary transition border-none placeholder:text-coal/50 flex gap-2 items-center">
            <input @if($field->required)
                       required
                   @endif wire:model.live="values.{{$field->fieldName}}" type="radio"
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
