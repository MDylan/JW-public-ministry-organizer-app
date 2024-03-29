<x-admin-layout>
    @section('title')
    {{__('user.updateProfile')}}
    @endsection
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
                            @if (session('success'))
                                <div class="alert alert-success">
                                    {{ session('success') }}
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
                                <div class="border border-secondary border-left-0 border-right-0 rounded p-2">
                                    <div class="row">                                    
                                        <div class="col-sm-6">
                                            <div class="form-group mb-3">
                                                <label for="name">@lang('Full name')</label>
                                                <input name="name" type="text" class="form-control @error('name') is-invalid @enderror" id="name" value="{{ auth()->user()->name }}" placeholder="@lang('Full name')" />
                                                @error('name')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="form-group mb-3">
                                                <label for="congregation">@lang('user.congregation'):  (@lang('app.not_required'))</label>
                                                <input name="congregation" type="text" class="form-control @error('congregation') is-invalid @enderror" id="congregation" value="{{ auth()->user()->congregation }}" placeholder="@lang('user.congregation')" />
                                                @error('congregation')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                            </div>
                                        </div>
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
                                                <label for="phone">@lang('user.phone'): (@lang('app.not_required'))</label>
                                                <input name="phone_number" type="number" class="form-control @error('phone_number') is-invalid @enderror" id="phone" value="{{ auth()->user()->phone_number }}" placeholder="@lang('user.phone')" aria-describedby="phoneHelpBlock" />
                            
                                                <span class="w-100"></span>
                                                <small id="phoneHelpBlock" class="text-muted">
                                                    @lang('user.phone_helper') <strong>362012345689</strong>
                                                </small>
                                                @error('phone')<div class="invalid-feedback" role="alert">{{__($message, ['attribute' => __('user.phone')])}}</div>@enderror
                                            </div>
                                        </div>
                                    </div>
                                    @if(auth()->user()->getPendingEmail())
                                        <div class="row">
                                            <div class="col-12 text-center">
                                                <div class="badge badge-warning">@lang('user.newEmail.pending', ['email' => auth()->user()->getPendingEmail()])</div>
                                                <br/>
                                                <a class="btn btn-info btn-sm" href="{{ route('user.resendNewEmailVerification') }}">
                                                    <i class="far fa-envelope mr-1"></i>
                                                    @lang('Resend Verification Email')</a>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="border border-secondary border-left-0 border-right-0 rounded p-2 mt-2">
                                    <div class="row">
                                        <div class="col-12">
                                            <h5><i class="fas fa-calendar-check mr-1"></i> @lang('user.calendars'):</h5>
                                            @lang('user.calendars_info')
                                        </div>
                                    </div>
                                    @foreach (config('events.calendars') as $calendar)
                                        <div class="ml-2 form-check">
                                            <input class="form-check-input" type="checkbox" name="calendars[{{$calendar}}]" value="1" id="calendar_{{ $calendar }}"
                                            @if (isset(auth()->user()->calendars[$calendar]) || old('calendars.'.$calendar))
                                                checked
                                            @endif>
                                            <label class="form-check-label" for="calendar_{{ $calendar }}" role="button">
                                                {{ __('user.calendar.'.$calendar) }}
                                            </label>
                                        </div>
                                    @endforeach
                                    @error('calendars_keys')<div class="alert alert-danger">{{ __($message) }}</div>@enderror
                                    <div class="form-group row">
                                        <label for="first_day" class="col-sm-4 col-form-label text-bold text-right">@lang('user.first_day_of_week'):</label>
                                        <div class="col-sm-8">
                                            <select name="firstDay" class="form-control @error('firstDay') is-invalid @enderror" id="first_day">
                                                <option value="0">@lang('group.days.0')</option>
                                                <option value="1" @if ($firstday == 1) selected @endif>@lang('group.days.1')</option>
                                            </select> 
                                            @error('firstDay')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                        </div>
                                      </div>
                                </div>
                                <div class="border border-secondary border-left-0 border-right-0 rounded mt-2 p-2">
                                    <div class="row">
                                        <div class="col-12">
                                            <h5><i class="fas fa-eye mr-1"></i> @lang('user.show_fields.title'):</h5>
                                            @lang('user.show_fields.help')
                                        </div>
                                    </div>
                                    <div class="ml-2 form-check">
                                        <input class="form-check-input" type="checkbox" name="show_fields[congregation]" value="1" id="show_congregation"
                                        @if (isset(auth()->user()->show_fields['congregation']) || old('show_fields.congregation'))
                                            checked
                                        @endif>
                                        <label class="form-check-label" for="show_congregation" role="button">
                                            {{ __('user.congregation') }}
                                        </label>
                                    </div>
                                    <div class="ml-2 form-check">
                                        <input class="form-check-input" type="checkbox" name="show_fields[phone]" value="1" id="show_phone"
                                        @if (isset(auth()->user()->show_fields['phone']) || old('show_fields.phone'))
                                            checked
                                        @endif>
                                        <label class="form-check-label" for="show_phone" role="button">
                                            {{ __('user.phone') }}
                                        </label>
                                    </div>
                                    <div class="ml-2 form-check">
                                        <input class="form-check-input" type="checkbox" name="show_fields[email]" value="1" id="show_email"
                                        @if (isset(auth()->user()->show_fields['email']) || old('show_fields.email'))
                                            checked
                                        @endif>
                                        <label class="form-check-label" for="show_email" role="button">
                                            {{ __('user.email') }}
                                        </label>
                                    </div>
                                    @error('show_fields_keys')<div class="alert alert-danger">{{ __($message) }}</div>@enderror
                                </div>
                                {{-- Opt out notifications --}}
                                <div class="border border-secondary border-left-0 border-right-0 rounded mt-2 p-2">
                                    <div class="row">
                                        <div class="col-12">
                                            <h5><i class="fas fa-envelope-open-text mr-1"></i> @lang('user.notifications.title'):</h5>
                                            @lang('user.notifications.help')
                                        </div>
                                    </div>
                                    @foreach ($notifications as $notification)
                                        <div class="ml-2 form-check">
                                            <input class="form-check-input" type="checkbox" name="opted_out_of_notifications[{{$notification}}]" value="1" id="not_{{ $notification }}"
                                            @if (isset(auth()->user()->opted_out_of_notifications[$notification]) || old('opted_out_of_notifications.'.$notification))
                                                checked
                                            @endif>
                                            <label class="form-check-label" for="not_{{ $notification }}" role="button">
                                                {{ __('user.notifications.list.'.$notification) }}
                                            </label>
                                        </div>
                                    @endforeach
                                    @error('opted_out_of_notifications')<div class="alert alert-danger">{{ __($message) }}</div>@enderror
                                </div>
                                <div class="d-flex justify-content-center mt-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save mr-1"></i>
                                        {{__('Save')}}
                                    </button>
                                </div>
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
                                    <label for="password" class="col-md-4 col-form-label text-md-right">@lang('New Password'):</label>

                                    <div class="col-md-8">
                                        <input id="new_password" type="password" class="form-control @error('password', 'updatePassword') is-invalid @enderror" name="password" required autocomplete="new-password" aria-describedby="passwordHelpBlock">
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
                                    <label for="password-confirm" class="col-md-4 col-form-label text-md-right">@lang('Confirm Password'):</label>

                                    <div class="col-md-8">
                                        <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                                    </div>
                                </div>

                                <div class="form-group row mb-0">
                                    <div class="col-md-8 offset-md-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa fa-key mr-1"></i>
                                            {{ __('Update Password') }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <div class="border border-secondary border-left-0 border-right-0 rounded p-2 mt-5">
                                <div class="row">
                                    <div class="col text-center">
                                        @lang('user.two_factor.help')
                                        <a href="{{ route('user.twofactorsettings') }}" class="btn btn-success mt-3">
                                            <i class="fas fa-lock mr-1"></i>
                                            @lang('user.two_factor.title')
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>

                    @if (Config::get('settings_gdpr'))
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                            <h5 class="m-0">@lang('user.gdpr.my_personal_datas')</h5>
                            </div>
                            <div class="card-body">
                                @lang('user.gdpr.info')
                                <form action="{{ route('gdpr-download') }}" method="POST">
                                    @csrf
                                    <div class="form-group row">
                                        <label for="password" class="col-md-4 col-form-label text-md-right">@lang('Current Password'):</label>

                                        <div class="col-md-8">
                                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="off">
                                            @error('password')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="form-group row mb-0">
                                        <div class="col-md-8 offset-md-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fa fa-download mr-1"></i>
                                                @lang('user.gdpr.download')
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>                                 
                    @endif

                    <div class="card card-primary card-outline">
                        <div class="card-header">
                        <h5 class="m-0">@lang('user.logout_other_devices')</h5>
                        </div>
                        <div class="card-body">
                            @lang('user.logout_info')
                            <form action="{{ route('user.logout_other_devices') }}" method="POST">
                                @csrf
                                <div class="form-group row">
                                    <label for="password" class="col-md-4 col-form-label text-md-right">@lang('Current Password'):</label>

                                    <div class="col-md-8">
                                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="off">
                                        @error('password')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="form-group row mb-0">
                                    <div class="col-md-8 offset-md-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sign-out-alt mr-1"></i>
                                            @lang('user.logout_other_devices')
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

            <div class="row">               

                <div class="col-md-6">
                    <div class="card card-danger card-outline collapsed-card">
                        <div class="card-header">
                            <h5 class="card-title">@lang('user.delete.title')</h5>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            @lang('user.delete.info')
                            <div class="alert alert-warning">
                                @lang('user.delete.alert')
                            </div>
                            <a href="{{ route('user.askToDelete') }}" class="btn btn-danger">
                                <i class="fa fa-trash mr-1"></i>
                                @lang('user.delete.button')
                            </a>
                        </div>
                    </div>
                </div>
            </div>


            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content -->
    </div>
</x-admin-layout>