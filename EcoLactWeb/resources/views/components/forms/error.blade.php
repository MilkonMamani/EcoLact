@props(['name'])

@error($name)
    <p {{ $attributes->class(['mt-1.5 text-xs text-danger']) }}>{{ $message }}</p>
@enderror
