@extends('public')

@section('title')@lang('user.register')@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 align-self-center">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="/" class="h1">{{__('app.title')}}</a>
            </div>
            <div class="card-body">

                <div class="alert alert-info">
                    @lang('app.registration_disabled')
                </div>

            </div>
            <div class="card-footer text-muted">
                <a href="{{route('login')}}" class="text-center">{{__('user.loginWithUser')}}</a>
            </div>
        </div>
    </div>
</div>
@endsection