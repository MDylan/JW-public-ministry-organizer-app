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
            @if (config('settings_registration') == 1)
                <p class="login-box-msg">{{__('user.registerMessage')}}</p>

                {!! Form::open() !!}
                @foreach ($nameFields as $field => $translate) 
                <div class="input-group mb-3">
                    {!! Form::text($field, '', ['class' => 'form-control'. ( $errors->has($field) ? ' is-invalid' : '' ), 'placeholder' => $translate]) !!}
                    <div class="input-group-append">
                        <div class="input-group-text">
                        <span class="fas fa-user"></span>
                        </div>
                    </div>
                    @error($field)<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                </div>            
                @endforeach
                <div class="input-group mb-3">
                    {!! Form::email('email', '', ['class' => 'form-control'. ( $errors->has('email') ? ' is-invalid' : '' ), 'placeholder' => __('user.email')]) !!}
                    <div class="input-group-append">
                        <div class="input-group-text">
                        <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                    @error('email')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                </div>
                <div class="input-group mb-3">
                    {!! Form::text('phone', '', [
                        'class' => 'form-control'. ( $errors->has('phone') ? ' is-invalid' : '' ), 
                        'placeholder' => __('user.phone'),
                        'aria-describedby' => 'phoneHelpBlock'
                    ]) !!}
                    <div class="input-group-append">
                        <div class="input-group-text">
                        <span class="fas fa-phone"></span>
                        </div>
                    </div>
                    <span class="w-100"></span>
                    <small id="phoneHelpBlock" class="text-muted">
                        @lang('user.phone_helper') <strong>362012345689</strong>
                    </small>
                    @error('phone')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                </div>   
                <div class="input-group mb-3">
                    {!! Form::password('password', ['class' => 'form-control'. ( $errors->has('password') ? ' is-invalid' : '' ), 'placeholder' => __('user.password')]) !!}
                    <div class="input-group-append">
                        <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                        </div>
                    </div>
                    @error('password')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                </div>
                <div class="input-group mb-3">
                    {!! Form::password('password_confirmation', ['class' => 'form-control'. ( $errors->has('password_confirmation') ? ' is-invalid' : '' ), 'placeholder' => __('user.passwordConfirmation')]) !!}
                    <div class="input-group-append">
                        <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                        </div>
                    </div>
                    @error('password_confirmation')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
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
                    <button type="submit" class="btn btn-primary btn-block">{{__('user.register')}}</button>
                </div>
                <!-- /.col -->
                </div>
                {!! Form::close() !!}
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