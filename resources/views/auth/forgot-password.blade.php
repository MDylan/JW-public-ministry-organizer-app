@extends('public')

@section('title')@lang('user.lostPassword') @endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 align-self-center">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="/" class="h1">{{__('app.title')}}</a>
            </div>
            <div class="card-body">
                <p class="text-center">{{ __('Reset Password') }}</p>
                <form method="POST" action="{{ route('password.email') }}" id="lostpasswordForm">
                    @csrf
                    @if (env('USE_RECAPTCHA', false))
                        <input type="hidden" class="g-recaptcha" name="recaptcha_token" id="recaptcha_token">
                    @endif
                    <div class="form-group row">
                        <label for="email" class="col-md-4 col-form-label text-md-right">{{ __('E-Mail Address') }}</label>

                        <div class="col-md-8">
                            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-md-8 offset-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key mr-1"></i>
                                {{ __('Send Password Reset Link') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer text-muted">
                <a href="{{route('login')}}">
                    <i class="fas fa-sign-in-alt mr-1"></i>
                    {{__('user.login')}}</a>
            </div>
        </div>
    </div>
</div>
@endsection
@if (env('USE_RECAPTCHA', false))
    @include('auth.recaptcha-script', ['formId' => 'lostpasswordForm'])
@endif