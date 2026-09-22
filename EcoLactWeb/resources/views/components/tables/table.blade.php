@props(['empty' => false])

@if ($empty)
    {{ $slot }}
@else
    <div class="ecolact-table-scroll -mx-5 px-5">
        <table {{ $attributes->class(['w-full min-w-[640px] border-collapse text-left text-sm']) }}>
            {{ $slot }}
        </table>
    </div>
@endif
