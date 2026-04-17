@props(['data'])
<ul class="popup-usp-list">
    @foreach ($data['items'] ?? [] as $item)
        <li>{{ $item['text'] ?? '' }}</li>
    @endforeach
</ul>
