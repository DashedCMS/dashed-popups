@props(['data'])
<p class="popup-paragraph">{!! nl2br(e($data['text'] ?? '')) !!}</p>
