@props(['status'])

@php
    $colors = [
        'pending' => 'bg-gray-100 text-gray-700',
        'processing' => 'bg-blue-100 text-blue-700',
        'submitted' => 'bg-green-100 text-green-700',
        'failed' => 'bg-red-100 text-red-700',
        'error' => 'bg-red-100 text-red-700',
    ];
    $class = $colors[$status] ?? 'bg-gray-100 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => "inline-block rounded-full px-2.5 py-0.5 text-xs font-medium $class"]) }}>
    {{ ucfirst($status) }}
</span>
