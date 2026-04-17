@props(['data'])
@if (! empty($data['image']))
    <img src="{{ mediaHelper()->getSingleMedia($data['image'], 'original')?->getFullUrl() ?? asset($data['image']) }}"
         alt="{{ $data['alt'] ?? '' }}"
         class="popup-image">
@endif
