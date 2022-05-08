@extends('layouts.setup')

@section('content')

    <div class="row justify-content-center">
        <div class="col-12 col-md-8">

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h5>@lang('setup.account_setup')</h5>
                </div>
                <div class="card-body">

                    <div class="progress progress-sm active mb-3">
                        <div class="progress-bar bg-success progress-bar-striped" role="progressbar" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100" style="width: 80%">
                        <span class="sr-only">80% Complete</span>
                        </div>
                    </div>

                    <p>@lang('setup.account_setup.intro')</p>

                    @include('setup.alert')

                    <form action="{{ route('setup.save-account') }}" method="post">
                        @csrf

                        <div class="mb-3">
                            <label for="name">
                                @lang('setup.account_setup.name')
                            </label>
                            <input type="text" name="name" id="name"
                                class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                placeholder="@lang('user.name')" aria-label="@lang('user.name')"
                                value="{{ old('name') }}" required autofocus>

                            @if ($errors->has('name'))
                                <p class="invalid-feedback" role="alert">
                                    {{ $errors->first('name') }}
                                </p>
                            @endif
                        </div>

                        <div class="mb-3">
                            <div class="row">
                                <div class="col 6">                                    
                                    <label for="email">
                                        @lang('setup.account_setup.email')
                                    </label>
                                    <input type="email" name="email" id="email"
                                        class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                        placeholder="@lang('user.email')" aria-label="@lang('user.email')"
                                        value="{{ old('email') }}" required autofocus>

                                    @if ($errors->has('email'))
                                        <p class="invalid-feedback" role="alert">
                                            {{ $errors->first('email') }}
                                        </p>
                                    @endif                                    
                                </div>
                                <div class="col-6">
                                    <label for="phone">
                                        @lang('user.phone')
                                    </label>
                                    <input type="number" name="phone_number" id="phone"
                                        class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                                        placeholder="@lang('user.phone')" aria-label="@lang('user.phone')"
                                        value="{{ old('phone_number') }}" required autofocus>

                                    @if ($errors->has('phone_number'))
                                        <p class="invalid-feedback" role="alert">
                                            {{ $errors->first('phone_number') }}
                                        </p>
                                    @endif   
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="row">
                                <div class="col-6">
                                    <label for="password">
                                        @lang('setup.account_setup.password')
                                    </label>
                                    <input type="password" name="password" id="password"
                                        class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                        value="{{ old('password') }}" aria-label="@lang('linkace.password')">
                                    @if ($errors->has('password'))
                                        <p class="invalid-feedback" role="alert">
                                            {{ $errors->first('password') }}
                                        </p>
                                    @else
                                        <p class="form-text text-muted small">
                                            @lang('setup.account_setup.password_requirements')
                                        </p>
                                    @endif
                                </div>
                                <div class="col-6">
                                    <label for="password_confirmation">
                                        @lang('setup.account_setup.password_confirmed')
                                    </label>
                                    <input type="password" name="password_confirmation" id="password_confirmation"
                                        class="form-control {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}"
                                        value="{{ old('password_confirmation') }}" aria-label="@lang('linkace.password_confirmed')">
                                    @if ($errors->has('password_confirmation'))
                                        <p class="invalid-feedback" role="alert">
                                            {{ $errors->first('password_confirmation') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            
                        </div>

                        <button type="submit" class="btn btn-primary">
                            @lang('setup.account_setup.create')
                            <i class="fas fa-arrow-right ml-1"></i>
                        </button>

                    </form>

                </div>
            </div>

        </div>
    </div>

@endsection