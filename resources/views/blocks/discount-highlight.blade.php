@props(['data'])
<div class="my-4 rounded-lg bg-gradient-to-br from-amber-100 to-amber-200 border border-amber-300 px-5 py-4 text-center shadow-sm">
    @if (! empty($data['label']))
        <div class="text-xs font-semibold uppercase tracking-wide text-amber-900/80">
            {{ $data['label'] }}
        </div>
    @endif
    <div class="mt-1 flex items-baseline justify-center gap-2">
        <span class="text-4xl font-extrabold text-amber-900 leading-none">
            {{ $data['value'] ?? '' }}
        </span>
        @if (! empty($data['suffix']))
            <span class="text-base font-semibold text-amber-900">
                {{ $data['suffix'] }}
            </span>
        @endif
    </div>
</div>
