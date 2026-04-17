@props(['data'])
@php($level = $data['level'] ?? 'h2')
<{{ $level }} class="popup-heading">{{ $data['text'] ?? '' }}</{{ $level }}>
