@extends('public')

@section('content')

<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
    
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item active">
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
            <li class="nav-item">
                <a class="nav-link" href="{{ route('login') }}">{{__('user.login')}}</a>
            </li>
            @endif
        @endif

      </ul>
    </div>
</div>
  </nav>
  <div class="container">
      <h1 class="mt-5">{{__('app.title')}}</h1>
      <p class="lead">
          Üdvözlünk!<br/>
          Ide még valamilyen alapvető bemutatkozó tartalmat fogunk írni hamarosan! :)
      </p>

  </div>

  @endsection