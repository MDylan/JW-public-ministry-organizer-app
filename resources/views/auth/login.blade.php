@extends('public')

@section('title')@lang('user.login')@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 align-self-center">
        <!-- /.login-logo -->
        <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="/" class="h1">{{__('app.title')}}</a>
        </div>
        <div class="card-body">
            {{-- @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif --}}
            <form action="{{route('login')}}" method="POST">
                @csrf
                <x-honey/>
                @if (env('USE_RECAPTCHA', false))
                    <x-honey-recaptcha/> 
                @endif
            <div class="input-group mb-3">
                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" placeholder="Email">
                <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-envelope"></span>
                </div>
                </div>
                @error('email')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
            </div>
            <div class="input-group mb-3">
                <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="{{__('user.password')}}">
                <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
                </div>
                @error('password')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
            </div>
            <div class="row">
                <div class="col-8">
                <div class="icheck-primary">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">
                    {{__('user.rememberMe')}}
                    </label>
                </div>
                </div>
                <!-- /.col -->
                <div class="col-4">
                <button type="submit" class="btn btn-primary btn-block">{{__('user.login')}}</button>
                </div>
                <!-- /.col -->
            </div>
            </form>
    
            
        </div>
        <!-- /.card-body -->
        <div class="card-footer text-muted">
            <div class="row">
                <div class="col text-center">
                    @if (Route::has('password.request'))
                        <a href="{{route('password.request')}}">{{__('user.lostPassword')}}</a>
                    @endif  
                </div>
                @if (config('settings_registration') == 1)
                    <div class="col text-center">
                        @if (Route::has('register'))
                            <a href="{{route('register')}}" class="text-center">{{__('user.register')}}</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        </div>
        <!-- /.card -->
    </div>
    <!-- /.login-box -->
</div>
@endsection