@props(['name'])

@switch($name)
    @case('grid')
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>
        @break
    @case('leaf')
        <svg viewBox="0 0 24 24"><path d="M20 4c-8 0-14 4-14 10 0 3 2 5 5 5 6 0 9-7 9-15Z"/><path d="M4 21c2-6 6-9 12-12"/></svg>
        @break
    @case('box')
        <svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7Z"/><path d="M12 11v10"/></svg>
        @break
    @case('layers')
        <svg viewBox="0 0 24 24"><path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/></svg>
        @break
    @case('cart')
        <svg viewBox="0 0 24 24"><path d="M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
        @break
    @case('chart')
        <svg viewBox="0 0 24 24"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/></svg>
        @break
    @case('coins')
        <x-heroicon-o-banknotes />
        @break
    @case('settings')
        <x-heroicon-o-cog-6-tooth />
@endswitch
