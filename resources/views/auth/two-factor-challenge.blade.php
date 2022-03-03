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
            <form action="/two-factor-challenge" method="POST">
                @csrf
                <div class="form-group mb-3">
                    <label for="name">@lang('user.two_factor.add_code')</label>
                    <input type="text" class="form-control @error('code') is-invalid @enderror" name="code" placeholder="" autocomplete="off">
                    @error('code')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                </div>     
                <button type="submit" class="btn btn-primary btn-block">{{__('user.login')}}</button>
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
                <div class="col text-center">
                    <a href="{{route('login')}}" class="text-center">{{__('user.login')}}</a>
                </div>
            </div>
        </div>
        </div>
        <!-- /.card -->
    </div>
    <!-- /.login-box -->
</div>
@endsection