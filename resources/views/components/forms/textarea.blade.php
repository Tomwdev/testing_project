@props(['label', 'name', 'rows' => 4])

@php
    $defaults = [
        'id' => $name,
        'name' => $name,
        'rows' => $rows,
        'class' => 'rounded-xl bg-white/10 border border-white/10 px-5 py-4 w-full',
    ];
@endphp

<x-forms.field :$label :$name>
    <textarea {{ $attributes($defaults) }}>{{ old($name, $slot) }}</textarea>
</x-forms.field>
