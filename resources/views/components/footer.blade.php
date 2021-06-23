@foreach ($menus as $menu) 
    @if ($menu->status === 0 && auth()->user()->can('is-admin') == false)
        @continue
    @endif
    | <a href="/page/{{ $menu->slug }}">{{ $menu->title }}</a>
@endforeach