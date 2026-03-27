{{-- ERP Top Navigation Dropdown Component --}}
{{-- Usage: <x-erp-nav-dropdown label="Masters" icon="database" :active="request()->routeIs(...)"> ... </x-erp-nav-dropdown> --}}
@props(['label', 'icon' => null, 'active' => false])

<li class="erp-menu-item erp-has-dropdown"
    role="none"
    x-data="{ open: false, closeTimer: null }"
    @erp-nav-open="clearTimeout(closeTimer); open = true"
    @erp-nav-close="clearTimeout(closeTimer); open = false"
    @click.outside="open = false">
    <button class="erp-menu-link {{ $active ? 'active' : '' }}"
            @click="open = (window.innerWidth < 992) ? !open : true"
            type="button"
            role="menuitem"
            :aria-expanded="open.toString()"
            aria-haspopup="true">
        @if($icon)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i>@endif
        <span>{{ $label }}</span>
        <i class="bi bi-chevron-down erp-chevron" aria-hidden="true" :style="open ? 'transform:rotate(180deg)' : ''"></i>
    </button>
    <div class="erp-dropdown" x-show="open" x-transition x-cloak style="display:none;" role="menu">
        {{ $slot }}
    </div>
</li>
