<div class="tracking-tight">
    <h3 class="font-semibold text-lg mb-2">{{ $field->labelName }}</h3>
    <p class="text-lg text-neutral-600 w-full">{{ $field->description }}</p>
    @if($field->helper_text)
        <span class="text-neutral text-sm">{{ $field->helper_text }}</span>
    @endif
</div>
