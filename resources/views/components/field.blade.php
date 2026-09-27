{{-- Label + control + inline validation message. --}}
@props(['label' => null, 'for' => null, 'error' => null, 'hint' => null, 'required' => false])
<div {{ $attributes->merge(['class' => '']) }}>
    @if ($label)
        <label @if($for) for="{{ $for }}" @endif class="form-label">{{ $label }} @if($required)<span class="text-rose-600" aria-hidden="true">*</span>@endif</label>
    @endif
    {{ $slot }}
    @if ($error)
        @error($error)<p class="mt-1 flex items-center gap-1 text-xs font-medium text-rose-700 dark:text-rose-400"><x-hicon name="exclamation-circle" class="size-4" />{{ $message }}</p>@enderror
    @endif
    @if ($hint)<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>@endif
</div>
