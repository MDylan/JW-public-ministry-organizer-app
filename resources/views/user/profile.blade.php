<x-admin-layout>
    <div>
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                <h1 class="m-0">{{__('user.updateProfile')}}</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{__('app.menu-home')}}</a></li>
                    <li class="breadcrumb-item active"><a href="{{route('user.profile')}}">{{__('app.profile')}}</a></li>
                </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <div class="content">
            <div class="container-fluid">
            <div class="row">
                <div class="col-lg-6">

                <div class="card card-primary card-outline">
                    <div class="card-header">
                    <h5 class="m-0">{{__('user.userData')}}</h5>
                    </div>
                    <div class="card-body">
                        @if (session('status') == "profile-information-updated")
                            <div class="alert alert-success">
                                @lang('user.profile_updated')
                            </div>
                        @endif
                        @if (session('profile_message'))
                        <div class="alert alert-danger">
                            {{session('profile_message')}}
                        </div>
                        @endif
                        <form method="POST" action="{{ route('user-profile-information.update')}}">
                            @csrf
                            @method("PUT")
                            <div class="row">
                                
                            @foreach (trans('user.nameFields') as $field => $translate) 
                            <div class="col-sm-6">
                                <div class="input-group mb-3">
                                    {!! Form::text($field, auth()->user()->$field, ['class' => 'form-control'. ( $errors->has($field) ? ' is-invalid' : '' ), 'placeholder' => $translate]) !!}
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                        <span class="fas fa-user"></span>
                                        </div>
                                    </div>
                                    @error($field)<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                            </div>
                            @endforeach
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                <div class="input-group mb-3">
                                    {!! Form::email('email', auth()->user()->email, ['class' => 'form-control'. ( $errors->has('email') ? ' is-invalid' : '' ), 'placeholder' => __('user.email')]) !!}
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                        <span class="fas fa-envelope"></span>
                                        </div>
                                    </div>
                                    @error('email')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="input-group mb-3">
                                        {!! Form::text('phone', auth()->user()->phone, [
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
                                        @error('phone')<div class="invalid-feedback" role="alert">{{__($message, ['attribute' => __('user.phone')])}}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">{{__('Save')}}</button>
                        </form>
                    </div>
                </div>

                </div>
                <!-- /.col-md-6 -->
                <div class="col-lg-6">

                <div class="card card-primary card-outline">
                    <div class="card-header">
                    <h5 class="m-0">{{__('Update Password')}}</h5>
                    </div>
                    <div class="card-body">
                        @if (session('status') == "password-updated")
                            <div class="alert alert-success">
                                @lang('user.password_updated')
                            </div>
                        @endif
                        <form method="POST" action="{{ route('user-password.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="form-group row">
                                <label for="current_password" class="col-md-4 col-form-label text-md-right">{{ __('Current Password') }}</label>

                                <div class="col-md-8">
                                    <input id="current_password" type="password" class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" name="current_password" required>

                                    @error('current_password', 'updatePassword')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('Password') }}</label>

                                <div class="col-md-8">
                                    <input id="password" type="password" class="form-control @error('password', 'updatePassword') is-invalid @enderror" name="password" required autocomplete="new-password">

                                    @error('password', 'updatePassword')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="password-confirm" class="col-md-4 col-form-label text-md-right">{{ __('Confirm Password') }}</label>

                                <div class="col-md-8">
                                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                                </div>
                            </div>

                            <div class="form-group row mb-0">
                                <div class="col-md-8 offset-md-4">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('Update Password') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                </div>
                <!-- /.col-md-6 -->
            </div>
            <!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content -->
    </div>
</x-admin-layout>