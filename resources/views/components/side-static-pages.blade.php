@foreach ($sidemenu as $menu) 
    @if ($menu->position !== 'left')
        @continue;
    @endif
    <li class="nav-item">
        <a href="{{ route('static_page', ['slug' => $menu->slug]) }}" class="nav-link {{ request()->is('page/'.$menu->slug) ? 'active' : '' }}">
            @if (auth()->user())
                <i class="nav-icon {{ $menu->icon }}"></i>
            @endif
            <p>
                {{ $menu->title }}
            </p>
        </a>
    </li>
@endforeach