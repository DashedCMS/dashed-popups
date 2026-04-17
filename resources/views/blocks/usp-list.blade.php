@props(['data'])
<ul class="my-3 space-y-2 text-sm text-gray-700">
    @foreach ($data['items'] ?? [] as $item)
        <li class="flex items-start gap-2">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-600" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 10.5l3 3 7-7"/>
            </svg>
            <span>{{ $item['text'] ?? '' }}</span>
        </li>
    @endforeach
</ul>
