<nav wire:ignore.self class="main-header navbar navbar-expand navbar-light  @if(($_COOKIE['currentTheme'] ?? 'light') == 'dark') navbar-dark @else navbar-wight @endif">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="{{route('home.home')}}" class="nav-link">{{__('app.menu-home')}}</a>
      </li>
      @if (session('loginback_url'))
      <li class="nav-item">
        <a href="{{ session('loginback_url') }}" class="nav-link btn btn-sm btn-warning">
          <i class="fas fa-backspace mr-1"></i>
          {{__('user.login_back')}}</a>
      </li>
      @endif
    </ul>
    @if (config('settings_maintenance') == 1) 
      <span class="navbar-text text-danger">
        @lang('app.maintenance_header')
      </span>  
    @endif   

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <li class="nav-item dropdown user-menu">
        <a href="{{route('user.profile')}}" class="nav-link dropdown-toggle">
          <i class="far fa-user mr-1"></i>
          <span class="d-none d-md-inline">
            {{ auth()?->user()?->name }}
            </span>
        </a>
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
            <a href="{{ $notification['route'] }}" class="dropdown-item" style="white-space: normal;">
                <i class="{{ $notification['icon'] }} mr-2"></i> 
                {{ $notification['message'] }}
            </a>
          @endforeach
        </div>
      </li>
      @if (count(Config('available_languages')) > 1)
        <li class="nav-item dropdown">
          <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="true">
            <i class="fa fa-globe"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-right p-0" style="left: inherit; right: 0px;">
            <p class="text-center my-1">@lang('app.choose_language')</p>
              @foreach (Config('available_languages') as $code => $value)
                @if (!$value['visible'] && (auth()->user()->role !== "mainAdmin" && auth()->user()->role !== "translator"))
                  @continue
                @endif
                <a href="{{ url()->current() }}?lang={{ $code }}" class="dropdown-item @if ($code == app()->getLocale() ) active @endif">
                 @if (!$value['visible'])
                 <i class="fas fa-eye-slash mr-1"></i>
                 @endif
                  {{ $value['name'] }}
                </a>
              @endforeach
          </div>
        </li>
      @endif
      <li class="nav-item">
        <a class="nav-link text-primary" id="toggle-button" data-widget="control-sidebar" data-slide="true" href="#" role="button">
          <i class="fas fa-calendar-check"></i>
        </a>
      </li>
    </ul>
  </nav>