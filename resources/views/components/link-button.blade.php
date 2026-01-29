@props(['href', 'variant' => 'primary'])

@php
    $classes = 'inline-block rounded py-2 px-6 font-bold transition-colors duration-300';

    $variants = [
        'primary' => 'bg-blue-800 hover:bg-blue-700 text-white',
        'secondary' => 'bg-white/10 hover:bg-white/20 text-white',
        'danger' => 'bg-red-800 hover:bg-red-700 text-white',
        'success' => 'bg-green-800 hover:bg-green-700 text-white',
    ];

    $classes .= ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

<a href="{{ $href }}" {{ $attributes(['class' => $classes]) }}>
    {{ $slot }}
</a>
