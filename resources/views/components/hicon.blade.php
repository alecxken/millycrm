@props(['name', 'solid' => false])
<x-dynamic-component :component="'heroicon-'.($solid ? 's' : 'o').'-'.$name" {{ $attributes->merge(['class' => 'size-5 shrink-0', 'aria-hidden' => 'true']) }} />
