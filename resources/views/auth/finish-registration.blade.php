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

                {{-- {!! Form::open() !!} --}}
                <form method="POST" action="{{route('finish_registration_register', ['id' => $id])}}?expires={{ app('request')->input('expires') }}&signature={{ app('request')->input('signature') }}" accept-charset="UTF-8">
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
                            <input type="email" disabled class="form-control @error('email') is-invalid @enderror" id="email" value="{{$user->email}}" placeholder="@lang('user.email')" />
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
                            <input name="password" type="password" class="form-control @error('password') is-invalid @enderror" id="password" value="" placeholder="@lang('user.password')" />
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
                        {!! Form::checkbox('terms', "yes", false, ['id' => 'agreeTerms', 'class' => ( $errors->has('terms') ? ' is-invalid' : '' )]) !!}
                    
                    <label for="agreeTerms">
                        {!! __('user.agreeTerms') !!}
                    </label>
                    @error('terms')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                    </div>
                </div>
                <!-- /.col -->
                <div class="col-sm-4">
                    <button type="submit" class="btn btn-primary btn-block">{{__('user.finish.button')}}</button>
                </div>
                <!-- /.col -->
                </div>
                {!! Form::close() !!}

        </div>
        <div class="card-footer text-muted">
            <a href="{{ $cancelUrl }}" onclick="return confirm('@lang('user.finish.cancelAlert')');" class="text-center">{{__('user.finish.cancel')}}</a>
        </div>
        <!-- /.form-box -->
    </div><!-- /.card -->
    </div>
    <!-- /.register-box -->
</div>
@endsection