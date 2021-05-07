<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="/" class="brand-link">
    <span class="brand-text font-weight-light">{{ __('app.title') }}</span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">
    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
              <a href="{{ route('home.home') }}" class="nav-link {{ request()->is('/') ? 'active' : '' }}">
                  <i class="nav-icon fas fa-home"></i>
                  <p>
                  {{ __('app.menu-home') }}
                  </p>
              </a>
          </li>
          @if (auth()->user()->email_verified_at)
          <li class="nav-item">
              <a href="{{route('groups')}}" class="nav-link {{ request()->is('groups') ? 'active' : '' }}">
                  <i class="nav-icon fas fa-users"></i>
                  <p>
                  {{ __('app.menu-groups') }}
                  </p>
              </a>
          </li>
            @can('is-admin')              
              <li class="nav-item nav-admin {{ request()->is('admin/*') ? 'menu-open' : '' }}">
                <a href="#" class="nav-link {{ request()->is('admin/*') ? 'active' : '' }}">
                  <i class="nav-icon fas fa-tachometer-alt"></i>
                  <p>
                    {{ __('app.menu-admin') }}
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="{{ route('admin.users') }}" class="nav-link {{ request()->is('admin/users') ? 'active' : '' }}">
                      <i class="fas fa-users nav-icon"></i>
                      <p>{{ __('app.menu-users') }}</p>
                    </a>
                  </li>
                </ul>
              </li>
          @endcan
        @endif
      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>