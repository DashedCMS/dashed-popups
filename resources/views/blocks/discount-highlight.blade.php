@props(['data'])
<div class="popup-discount-highlight">
    <span class="popup-discount-label">{{ $data['label'] ?? '' }}</span>
    <span class="popup-discount-value">{{ $data['value'] ?? '' }}</span>
    <span class="popup-discount-suffix">{{ $data['suffix'] ?? '' }}</span>
</div>
