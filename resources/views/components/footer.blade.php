<div class="footer-menu">
    @foreach ($sidemenu as $menu) 
        @if ($menu->position !== 'bottom')
            @continue;
        @endif
        @if ($menu->status === 0 && auth()->user()->can('is-admin') == false)
            @continue
        @endif
        <a class="footer-link" href="/page/{{ $menu->slug }}">{{ $menu->title }}</a>
    @endforeach
</div>