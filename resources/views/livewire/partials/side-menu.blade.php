<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="/" class="brand-link overflow-hidden">
      <span class="brand-text font-weight-light ml-1">
        <img src="{{ asset('favicon.png') }} " alt="@lang('app.title')" class="brand-image img-circle elevation-3" style="opacity: .8">
        {{ __('app.title') }}</span>
    </a>
  
    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
            <li class="nav-item">
                <a href="{{ route('home.home') }}" class="nav-link {{ $request_path == 'home' ? 'active' : '' }}">
                    <i class="nav-icon fas fa-home"></i>
                    <p>
                    {{ __('app.menu-home') }}
                    </p>
                </a>
            </li>
            @if (auth()->user()->email_verified_at)
              @if ($sideMenu['groups'])
                <li class="nav-item">
                  <a href="{{route('calendar')}}" class="nav-link {{ $request_path == 'calendar' ? 'active' : '' }}">
                      <i class="nav-icon fas fa-calendar-alt"></i>
                      <p>
                      {{ __('app.menu-calendar') }}
                      </p>
                  </a>
                </li>
              @endif
              <li class="nav-item">
                  <a href="{{route('groups')}}" class="nav-link {{ $request_path == 'groups' ? 'active' : '' }}">
                      <i class="nav-icon fas fa-users"></i>
                      <p>
                      {{ __('app.menu-groups') }}
                      @if ($sideMenu['invites'])
                        <span class="right badge badge-danger">{{ $sideMenu['invites'] }} @lang('app.new')</span>
                      @endif
                      </p>
                  </a>
              </li>
              <li class="nav-item">
                <a href="{{route('lastevents')}}" class="nav-link {{ $request_path == 'lastevents' ? 'active' : '' }}">
                    <i class="nav-icon fa fa-history"></i>
                    <p>
                    {{ __('app.menu-lastevents') }}
                    </p>
                </a>
              </li>

              <x-SideStaticPages></x-SideStaticPages> 
              
              @can('is-translator')              
                <li class="nav-item nav-admin {{ request()->is('admin/*') ? 'menu-open' : '' }}">
                  <a href="#" class="nav-link {{ request()->is('admin/*') ? 'active' : '' }}">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <p>
                      {{ __('app.menu-admin') }}
                      <i class="right fas fa-angle-left"></i>
                    </p>
                  </a>
                  <ul class="nav nav-treeview">
                    @can('is-admin') 
                      <li class="nav-item">
                        <a href="{{ route('admin.users') }}" class="nav-link {{ request()->is('admin/users') ? 'active' : '' }}">
                          <i class="fas fa-users nav-icon"></i>
                          <p>{{ __('app.menu-users') }}</p>
                        </a>
                      </li>
                      <li class="nav-item">
                        <a href="{{ route('admin.staticpages') }}" class="nav-link {{ request()->is('admin/staticpages*') ? 'active' : '' }}">
                          <i class="fa fa-file-alt nav-icon"></i>
                          <p>{{ __('app.menu-staticpages') }}</p>
                        </a>
                      </li>
                      <li class="nav-item">
                        <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->is('admin/settings') ? 'active' : '' }}">
                          <i class="fas fa-cog nav-icon"></i>
                          <p>{{ __('app.menu-settings') }}</p>
                        </a>
                      </li>
                    @endcan
                    <li class="nav-item">
                      <a href="{{ route('admin.translate') }}" class="nav-link {{ request()->is('admin/translate') ? 'active' : '' }}">
                        <i class="fa fa-language nav-icon"></i>
                        <p>{{ __('app.menu-translation') }}</p>
                      </a>
                    </li>
                  </ul>
                </li>
            @endcan
          @endif

          <li class="nav-item">
            <a href="{{route('user.profile')}}" class="nav-link {{ request()->is('user/profile') ? 'active' : '' }}">
                <i class="nav-icon fa fa-id-card"></i>
                <p>
                {{ __('app.profile') }}
                </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="{{route('logout')}}" class="nav-link text-warning" onclick="event.preventDefault(); 
            document.getElementById('logout-form').submit();">
                <i class="nav-icon fa fa-sign-out-alt"></i>
                <p>
                {{ __('app.logout') }}
                </p>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                  @csrf
                </form>
            </a>
          </li>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>