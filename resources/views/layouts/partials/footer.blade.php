<footer class="main-footer text-sm">
    <div class="w-100">
        <div class="d-flex flex-sm-row flex-column justify-content-between align-items-center">
            <div class="p-1">
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
            <div class="p-1">
                <div class="input-group">
                    <div class="input-group-prepend">
                      <div class="input-group-text"><i class="fas fa-adjust"></i></div>
                    </div>
                    <select id="theme_selector" class="form-control">
                        <option value="system">@lang('app.theme.system')</option>
                        <option value="light">@lang('app.theme.light')</option>
                        <option value="dark">@lang('app.theme.dark')</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</footer>