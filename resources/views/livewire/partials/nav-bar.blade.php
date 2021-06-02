<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="{{route('home.home')}}" class="nav-link">{{__('app.menu-home')}}</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <li class="nav-item dropdown user-menu">
        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
          <i class="far fa-user"></i>
          <span class="d-none d-md-inline">
            @foreach (trans('user.nameFields') as $field => $translate) 
              {{ auth()->user()->$field }}
            @endforeach
            </span>
        </a>
        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0px;">
          <!-- User image -->
          <li class="user-header bg-primary">
            <p>
              @foreach (trans('user.nameFields') as $field => $translate) 
                {{ auth()->user()->$field }}
              @endforeach
              <small>{{ __('roles.'.auth()->user()->role) }}</small>
            </p>
          </li>
          <!-- Menu Footer-->
          <li class="user-footer">
            <a href="{{ route('user.profile') }}" class="btn btn-default btn-flat">{{__('app.profile')}}</a>
            <a href="{{ route('logout') }}" class="btn btn-default btn-flat float-right"onclick="event.preventDefault(); 
            document.getElementById('logout-form').submit();">{{__('app.logout')}}</a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
              @csrf
            </form>
          </li>
        </ul>
      </li>

      <!-- Messages Dropdown Menu -->
      <!-- Notifications Dropdown Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          @if ( $total_notifications > 0 )
          <span class="badge badge-warning navbar-badge">{{ $total_notifications }}</span>    
          @endif          
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-header">
            @if ( $total_notifications > 0 )
            {{ __('app.total_notifications', ['number' => $total_notifications]) }}
            @else
                @lang('app.no_notification')
            @endif
        </span>
          @foreach ($notifications as $notification)
            <div class="dropdown-divider"></div>  
            <a href="{{ $notification['route'] }}" class="dropdown-item">
                <i class="fas {{ $notification['icon'] }} mr-2"></i> 
                {{ $notification['message'] }}
            </a>
          @endforeach
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
          <i class="fas fa-expand-arrows-alt"></i>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="toggle-button" data-widget="control-sidebar" data-slide="true" href="#" role="button">
          <i class="fas fa-th-large"></i>
        </a>
      </li>
    </ul>
  </nav>