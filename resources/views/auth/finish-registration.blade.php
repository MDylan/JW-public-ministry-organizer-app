@extends('public')

@section('title')@lang('user.finish.registration')@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8 align-self-center">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="/" class="h1">{{__('user.finish.registration')}}</a>
        </div>
        <div class="card-body">
                <p class="login-box-msg">{{__('user.finish.helper')}}</p>
                <form method="POST" action="{{route('finish_registration_register', ['id' => $id])}}?expires={{ app('request')->input('expires') }}&signature={{ app('request')->input('signature') }}" accept-charset="UTF-8">
                @csrf
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group mb-3">
                            <label for="name">@lang('Full name')</label>
                            <input name="name" type="text" class="form-control @error('name') is-invalid @enderror" id="name" value="{{ old('name') }}" placeholder="@lang('Full name')" />
                            @error('name')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        </div>     
                    </div>       
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="email">@lang('user.email'):</label>
                            <input type="email" disabled class="form-control @error('email') is-invalid @enderror" id="email" value="{{$user->email}}" placeholder="@lang('user.email')" />
                            @error('email')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="phone">@lang('user.phone'): (@lang('app.not_required'))</label>
                            <input name="phone_number" type="number" class="form-control @error('phone_number') is-invalid @enderror" id="phone" value="{{ old('phone_number') }}" placeholder="@lang('user.phone')" aria-describedby="phoneHelpBlock" />
                        <span class="w-100"></span>
                        <small id="phoneHelpBlock" class="text-muted">
                            @lang('user.phone_helper') <strong>362012345689</strong>
                        </small>
                        @error('phone_number')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
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
                    <div class="col-12">
                        <div class="alert alert-info"><i class="fas fa-info mx-1"></i> @lang('user.finish.info')</div>
                    </div>
                </div>
                <div class="row">
                <div class="col-sm-8">
                    @if (config('settings_terms_checkbox') == 1)
                        <div class="icheck-primary">
                            <input id="agreeTerms" class="@error('terms') is-invalid @enderror" name="terms" type="checkbox" value="yes">
                            <label for="agreeTerms">
                                {!! __('user.agreeTerms') !!}
                            </label>
                            @error('terms')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        </div>
                    @endif
                </div>
                <!-- /.col -->
                <div class="col-sm-4">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="far fa-check-circle mr-1"></i>
                        {{__('user.finish.button')}}</button>
                </div>
                <!-- /.col -->
                </div>
                </form>

        </div>
        <div class="card-footer text-muted">
            <a href="{{ $cancelUrl }}" onclick="return confirm('@lang('user.finish.cancelAlert')');" class="text-center btn btn-sm btn-warning">
                <i class="fas fa-times mr-1"></i>
                {{__('user.finish.cancel')}}</a>
        </div>
        <!-- /.form-box -->
    </div><!-- /.card -->
    </div>
    <!-- /.register-box -->
</div>
@endsection