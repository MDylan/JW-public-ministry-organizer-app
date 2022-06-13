<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" type="image/jpg" href="{{ asset('pmo-favicon.png') }}"/>
  <title>@lang('app.title') | @yield('title')</title>
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
  <!-- Theme style -->
  <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/public_style.css') }}">
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
    <div class="container">
      <a href="/" class="navbar-brand">
        <span class="brand-text font-weight-light">@lang('app.title')</span>
      </a>

      <button class="navbar-toggler order-1" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse order-3" id="navbarCollapse">
        <!-- Left navbar links -->

        <ul class="navbar-nav">

          <li class="nav-item {{ request()->is('/') ? 'active' : '' }}"">
            <a class="nav-link" href="/">{{__('app.menu-home')}}</a>
          </li>
          @if (Route::has('login'))
              @auth
              <li class="nav-item">
                <a class="nav-link" href="{{ route('logout') }}" onclick="event.preventDefault(); 
                document.getElementById('logout-form').submit();">{{__('app.logout')}}</a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                  @csrf
                </form>
              </li>  
              @else            
              <li class="nav-item {{ request()->is('login') ? 'active' : '' }}">
                  <a class="nav-link" href="{{ route('login') }}">{{__('user.login')}}</a>
              </li>
              @endif
          @endif

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
        </ul>
      </div>

      <!-- Right navbar links -->
      <ul class="order-1 order-md-3 navbar-nav navbar-no-expand ml-auto">
        @if (count(Config('available_languages')) > 1)
          <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true">
              <i class="fa fa-globe"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right p-0" style="left: inherit; right: 0px;">
              <p class="text-center my-1">@lang('app.choose_language')</p>
              @foreach (Config('available_languages') as $code => $value)
                @if (!$value['visible'])
                  @continue
                @endif
                <a href="{{ url()->current() }}?lang={{ $code }}" class="dropdown-item @if ($code == app()->getLocale() ) active @endif">
                  {{ $value['name'] }}
                </a>
              @endforeach
            </div>
          </li>
        @endif
      </ul>
    </div>
  </nav>
  <!-- /.navbar -->

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">@yield('title')</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="/">@lang('app.menu-home')</a></li>
              <li class="breadcrumb-item active">@yield('title')</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
      <div class="container">
        <div class="row">
          <div class="col-md-12">

            @if (config('settings_maintenance') == 1) 
              <div class="callout callout-danger">
                <h5>@lang('app.maintenance')</h5>
                <p>@lang('app.maintenance_mode')</p>
              </div>
            @endif
            @if(Session::has('status'))
              <p class="alert alert-success">{{ Session::get('status') }}</p>
            @endif
            
            @yield('content')

          </div>
        </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
      @include('cookie-consent::index')
    </div>
    <!-- /.content -->
    
  </div>
  <!-- /.content-wrapper -->

  

  <!-- Main Footer -->
  @include('layouts.partials.footer')
</div>
<!-- ./wrapper -->
<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
<!-- Bootstrap 4 -->
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<!-- AdminLTE App -->
<script src="{{ asset('dist/js/adminlte.min.js') }}"></script>
<script src="{{ asset('js/custom.js') }}?ver={{ filemtime(public_path('js/custom.js')) }}"></script>
@yield('footer_scripts')

</body>
</html>