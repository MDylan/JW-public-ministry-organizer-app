<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="/" class="brand-link">
    <span class="brand-text font-weight-light">{{ __('app.title') }}</span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">
    <!-- Sidebar user panel (optional) -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <img src="{{ asset('dist/img/user2-160x160.jpg') }}" class="img-circle elevation-2" alt="User Image">
      </div>
      <div class="info">
        <a href="#" class="d-block">Alexander Pierce</a>
      </div>
    </div>

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
          {{-- <li class="nav-item">
              <a href="/" class="nav-link">
                  <i class="nav-icon fas fa-users"></i>
                  <p>
                  {{ __('app.menu-users') }}
                  </p>
              </a>
          </li> --}}
        <li class="nav-item menu-open">
          <a href="#" class="nav-link {{ request()->is('admin/*') ? 'active' : '' }}">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>
              {{ __('app.menu-admin') }}
              <i class="right fas fa-angle-left"></i>
            </p>
          </a>
          <ul class="nav nav-treeview">
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="far fa-circle nav-icon"></i>
                <p>Active Page</p>
              </a>
            </li>
            <li class="nav-item">
              <a href="{{ route('admin.users') }}" class="nav-link {{ request()->is('admin/users') ? 'active' : '' }}">
                <i class="fas fa-users nav-icon"></i>
                <p>{{ __('app.menu-users') }}</p>
              </a>
            </li>
          </ul>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="nav-icon fas fa-th"></i>
            <p>
              Simple Link
              <span class="right badge badge-danger">New</span>
            </p>
          </a>
        </li>
      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>