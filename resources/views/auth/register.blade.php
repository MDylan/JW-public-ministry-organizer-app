@extends('public')

@section('title')@lang('user.register')@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8 align-self-center">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="/" class="h1">{{__('app.title')}}</a>
        </div>
        <div class="card-body">
            @if (config('settings_registration') == 1)
                <p class="login-box-msg">{{__('user.registerMessage')}}</p>

                <form method="POST" action="{{route('register')}}">
                @csrf
                <div class="row">
                    @foreach ($nameFields as $field => $translate) 
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="{{$field}}">{{$translate}}:</label>
                                <input name="{{$field}}" type="text" class="form-control @error($field) is-invalid @enderror" id="{{$field}}" value="{{ old($field) }}" placeholder="{{$translate}}" />
                                @error($field)<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                            </div>     
                        </div>       
                @endforeach
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="email">@lang('user.email'):</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email" value="{{ old('email') }}" placeholder="@lang('user.email')" />
                            @error('email')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="phone">@lang('user.phone'):</label>
                            <input name="phone" type="number" class="form-control @error('phone') is-invalid @enderror" id="phone" value="{{ old('phone') }}" placeholder="@lang('user.phone')" aria-describedby="phoneHelpBlock" />
                        <span class="w-100"></span>
                        <small id="phoneHelpBlock" class="text-muted">
                            @lang('user.phone_helper') <strong>362012345689</strong>
                        </small>
                        @error('phone')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                    </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="password">@lang('user.password'):</label>
                            <input name="password" type="password" class="form-control @error('password') is-invalid @enderror" id="password" value="" placeholder="@lang('user.password')" aria-describedby="passwordHelpBlock" />
                            <small id="passwordHelpBlock" class="form-text text-muted">
                                @lang('user.password_info')
                            </small>
                            @error('password')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="password_confirmation">@lang('user.passwordConfirmation'):</label>
                            <input name="password_confirmation" type="password" class="form-control @error('password') is-invalid @enderror" id="password_confirmation" value="" placeholder="@lang('user.passwordConfirmation')" />
                            @error('password_confirmation')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                <div class="col-sm-8">
                    <div class="icheck-primary">
                        <input id="agreeTerms" class="@error('terms') is-invalid @enderror" name="terms" type="checkbox" value="yes">
                  
                    <label for="agreeTerms">
                        {!! __('user.agreeTerms') !!}
                    </label>
                    @error('terms')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                    </div>
                </div>
                <!-- /.col -->
                <div class="col-sm-4">
                    <button type="submit" class="btn btn-primary btn-block">{{__('user.register')}}</button>
                </div>
                <!-- /.col -->
                </div>
            </form>
            @else
                <div class="alert alert-info">
                    @lang('app.registration_disabled')
                </div>
            @endif
        </div>
        <div class="card-footer text-muted">
            <a href="{{route('login')}}" class="text-center">{{__('user.loginWithUser')}}</a>
        </div>
        <!-- /.form-box -->
    </div><!-- /.card -->
    </div>
    <!-- /.register-box -->
</div>
@endsection