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
            @if (session('verified'))
                <div class="alert alert-success">
                    <i class="far fa-thumbs-up mr-1"></i>
                    @lang('user.newEmail.success') @lang('user.newEmail.please_use_it')
                </div>                
            @endif
            <form action="{{route('login')}}" method="POST" id="loginForm">
                @csrf
                @if (env('USE_RECAPTCHA', false))
                    <input type="hidden" class="g-recaptcha" name="recaptcha_token" id="recaptcha_token">
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
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt mr-1"></i>
                    {{__('user.login')}}</button>
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
                        <a href="{{route('password.request')}}">
                            <i class="fas fa-key mr-1"></i>
                            {{__('user.lostPassword')}}</a>
                    @endif  
                </div>
                @if (config('settings_registration') == 1)
                    <div class="col text-center">
                        @if (Route::has('register'))
                            <a href="{{route('register')}}" class="text-center">
                            <i class="far fa-address-card mr-1"></i>
                            {{__('user.register')}}</a>
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
@if (env('USE_RECAPTCHA', false))
    @include('auth.recaptcha-script', ['formId' => 'loginForm'])
@endif