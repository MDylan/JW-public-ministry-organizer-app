<footer class="main-footer text-sm">
    <div class="w-100">
        <div class="row">
            <div class="col-md-10 pt-1">
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
            </div>
            <div class="col-md-2">
                <div class="custom-control custom-switch theme-switch pt-1" style="float:right;">        
                    <input type="checkbox" class="custom-control-input" id="customSwitch1"> @lang('app.dark_theme')
                    <label class="custom-control-label" for="customSwitch1" style="float:left;"></label>                
                </div>
            </div>
        </div>
    </div>
</footer>