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
                <div class="col-md-6">
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
                                    <div class="form-group mb-3">
                                        <label for="{{$field}}">{{$translate}}:</label>
                                        <input name="{{$field}}" type="text" class="form-control @error($field) is-invalid @enderror" id="{{$field}}" value="{{ auth()->user()->$field }}" placeholder="{{$translate}}" />
                                        @error($field)<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                    </div>
                                </div>
                                @endforeach
                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                    <div class="form-group mb-3">
                                        <label for="email">@lang('user.email'):</label>
                                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email" value="{{ auth()->user()->email }}" placeholder="@lang('user.email')" />
                                        @error('email')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                    </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group mb-3">
                                            <label for="phone">@lang('user.phone'):</label>
                                            <input name="phone" type="number" class="form-control @error('phone') is-invalid @enderror" id="phone" value="{{ auth()->user()->phone }}" placeholder="@lang('user.phone')" aria-describedby="phoneHelpBlock" />
                        
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
                <div class="col-md-6">

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
                                <label for="current_password" class="col-md-4 col-form-label text-md-right">{{ __('Current Password') }}:</label>

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
                                <label for="password" class="col-md-4 col-form-label text-md-right">@lang('New Password')</label>

                                <div class="col-md-8">
                                    <input id="password" type="password" class="form-control @error('password', 'updatePassword') is-invalid @enderror" name="password" required autocomplete="new-password" aria-describedby="passwordHelpBlock">
                                    <small id="passwordHelpBlock" class="form-text text-muted">
                                        @lang('user.password_info')
                                    </small>
                                    @error('password', 'updatePassword')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="password-confirm" class="col-md-4 col-form-label text-md-right">@lang('Confirm Password')</label>

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