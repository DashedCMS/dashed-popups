<div class="
    h-full
    flex
    flex-col
    gap-4
    border-t-2
    sm:col-span-2
    xl:px-0
    xl:max-w-7xl xl:mx-auto">
    <header>
        <h3 class="font-semibold text-lg">{{ $field->labelName }}</h3>
        @if($field->description)
            <p class="text-lg text-neutral-600 w-full">{{ $field->description }}</p>
        @endif
    </header>
    <div class="col-span-3 grid grid-cols-2 md:grid-cols-4 gap-8">
        @foreach($field->images as $image)
            <div @class([
        'grid gap-2 p-1 text-neutral-600 cursor-pointer space-y-4 rounded-lg',
        'border-4 border-green-500' => $image['image'] == $values[$field->fieldName],
])
                 wire:click="setValueForField('{{$field->fieldName}}', '{{ $image['image'] }}')">
                <x-drift::image
                        class="w-full h-fit rounded-lg"
                        config="dashed"
                        :path="$image['image']"
                        :alt="$image['name'] ?? ''"
                        :manipulations="[
                            'fit' => [400,400],
                        ]"
                />
                @if($image['name'] ?? false)
                    <h4 class="font-semibold">{{ $image['name'] }}</h4>
                @endif
            </div>
        @endforeach
    </div>
    @if($field->helper_text)
        <span class="text-neutral text-sm">{{ $field->helper_text }}</span>
    @endif
    @error('values.' . $field->fieldName) <span class="text-red-500 font-bold">{{ $message }}</span> @enderror
</div>
