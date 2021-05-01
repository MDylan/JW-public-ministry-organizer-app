@extends('public')

@section('title') | __('user.login')@endsection

@section('content')
<div class="login-page">
    <div class="login-box">
        <!-- /.login-logo -->
        <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="/" class="h1">{{__('app.title')}}</a>
        </div>
        <div class="card-body">
    
            <form action="{{route('login')}}" method="post">
            <div class="input-group mb-3">
                <input type="email" class="form-control" placeholder="Email">
                <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-envelope"></span>
                </div>
                </div>
            </div>
            <div class="input-group mb-3">
                <input type="password" class="form-control" placeholder="{{__('user.password')}}">
                <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
                </div>
            </div>
            <div class="row">
                <div class="col-8">
                <div class="icheck-primary">
                    <input type="checkbox" id="remember">
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
    
            <p class="mb-1">
            <a href="{{route('password.update')}}">{{__('user.lostPassword')}}</a>
            </p>
            <p class="mb-0">
            <a href="{{route('register')}}" class="text-center">{{__('user.register')}}</a>
            </p>
        </div>
        <!-- /.card-body -->
        </div>
        <!-- /.card -->
    </div>
    <!-- /.login-box -->
</div>
@endsection