@props(['label', 'icon', 'active' => false])
<details class="ecolact-sidebar-group @if($active) is-active @endif" @if($active) open @endif>
    <summary><x-layout.sidebar-icon :name="$icon" /><span>{{ $label }}</span><x-layout.sidebar-icon name="chevron" class="ecolact-group-chevron" /></summary>
    <div class="ecolact-sidebar-submenu">{{ $slot }}</div>
</details>
