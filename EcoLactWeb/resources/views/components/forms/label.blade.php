@props(['for' => null, 'required' => false])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->class(['mb-1.5 block text-sm font-medium text-text']) }}>
    {{ $slot }}
    @if ($required)
        <span class="text-danger" aria-hidden="true">*</span>
    @endif
</label>
