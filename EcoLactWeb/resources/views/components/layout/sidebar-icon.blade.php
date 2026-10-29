@props(['name' => 'home'])
<svg {{ $attributes->class(['ecolact-nav-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('milk') <path d="M8 3h8v4H8zM8 7l-2 4v10h12V11l-2-4M9 12h6M3 12v5m18-5v5"/> @break
        @case('quality') <path d="M9 3h6m-5 0v7L5 20h14l-5-10V3M8 15h8"/> @break
        @case('production') <path d="m3 10 9-7 9 7H3Zm2 3h14M6 13v7m6-7v7m6-7v7M3 21h18"/> @break
        @case('recipe') <path d="M6 3h12a2 2 0 0 1 2 2v16H7a3 3 0 0 1-3-3V5a2 2 0 0 1 2-2Zm2 0v18m4-13h5m-5 4h5m-5 4h4"/> @break
        @case('factory') <path d="M3 21V11l6 3V9l6 4V6h4v7l2-1v9H3Zm3-3h2m3 0h2m3 0h2"/> @break
        @case('inventory') <path d="m3 6 4-2 4 2-4 2-4-2Zm0 0v5l4 2 4-2V6m-4 2v5m2 2 4-2 4 2-4 2-4-2Zm0 0v5l4 2 4-2v-5m-4 2v5m2-13 4-2 4 2-4 2-4-2Zm0 0v5l4 2 4-2V6m-4 2v5"/> @break
        @case('users') <circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3H2ZM16 4a3 3 0 0 1 0 6m3 11h3v-3a7 7 0 0 0-4-6"/> @break
        @case('pin') <path d="M19 10c0 6-7 12-7 12S5 16 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="9" r="2.5"/> @break
        @case('chat') <path d="M4 4h16v12H9l-5 4V4Zm4 5h8m-8 3h5"/> @break
        @case('wallet') <path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/> @break
        @case('payment') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 10h18m-12 6 2 2 4-4"/> @break
        @case('bell') <path d="M18 9a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8Zm-9 11a3 3 0 0 0 6 0"/> @break
        @case('audit') <path d="M6 3h12v18H6zM3 7h3m-3 5h3m-3 5h3m3-9h6m-6 4h6m-6 4h4"/> @break
        @case('box') <path d="M21 8 12 3 3 8l9 5 9-5Zm0 0v8l-9 5-9-5V8m9 5v8"/> @break
        @case('package') <path d="M21 8 12 3 3 8l9 5 9-5Zm0 0v8l-9 5-9-5V8m9 5v8M12 3v10"/> @break
        @case('receipt') <path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/> @break
        @case('sales') <circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M3 4h2l2.4 12.2a2 2 0 0 0 2 1.8h7.2a2 2 0 0 0 2-1.6L21 8H6"/> @break
        @case('settings') <path d="m10 2-1 3-3 1-3-1-2 4 3 2v3l-2 2 2 4 3-1 3 2h4l1-3 3-1 3 1 2-4-3-2v-3l2-2-2-4-3 1-3-2Z"/><circle cx="12" cy="12" r="3"/> @break
        @case('qr-code')
        @case('qr') <path d="M3 3h6v6H3zm12 0h6v6h-6ZM3 15h6v6H3zm12 0h3v3h3v3h-6zm6-3v3M12 3v3m0 6H6m6 6v3"/> @break
        @case('chevron') <path d="m9 5 7 7-7 7"/> @break
        @case('logout') <path d="M9 4H4v16h5m6-13 5 5-5 5m-6-5h11"/> @break
        @default <path d="m2 11 10-9 10 9h-3v10h-5v-7h-4v7H5V11Z" fill="currentColor" stroke="none"/>
    @endswitch
</svg>
