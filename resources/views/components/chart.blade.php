{{-- Chart.js canvas. Re-keyed when data changes so wire:poll refreshes it. --}}
@props(['config', 'height' => 'h-64', 'label' => 'Chart'])
<div wire:key="chart-{{ md5(json_encode($config)) }}" x-data="chart(@js($config))" wire:ignore {{ $attributes->merge(['class' => "relative $height"]) }}>
    <canvas x-ref="canvas" role="img" aria-label="{{ $label }}"></canvas>
</div>
