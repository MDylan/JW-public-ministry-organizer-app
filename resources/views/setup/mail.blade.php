@extends('layouts.setup')

@section('content')

    <div class="row justify-content-center">
        <div class="col-12 col-md-8">

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h5>@lang('settings.mail')</h5>
                </div>
                <div class="card-body">

                    <div class="progress progress-sm active mb-3">
                        <div class="progress-bar bg-success progress-bar-striped" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: 60%">
                        <span class="sr-only">60% Complete</span>
                        </div>
                    </div>

                    <p>@lang('setup.mail_intro')</p>

                    @include('setup.alert')

                    <form action="{{ route('setup.save-mail') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="mail_from_address">@lang('settings.mail_from_address'):</label>
                                    <input name="MAIL_FROM_ADDRESS" value="{{ old('MAIL_FROM_ADDRESS') }}" type="email" class="form-control{{ $errors->has('MAIL_FROM_ADDRESS') ? ' is-invalid' : '' }}" id="mail_from_address">
                                    @error('MAIL_FROM_ADDRESS')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="mail_mailer">@lang('settings.mail_mailer'):</label>
                                    <select name="MAIL_MAILER" class="form-control{{ $errors->has('MAIL_MAILER') ? ' is-invalid' : '' }}" id="mail_mailer">
                                        <option value="smtp" @if(!old('MAIL_MAILER') || old('MAIL_MAILER') == "smtp") selected @endif>smtp</option>
                                        <option value="phpmail" @if(old('MAIL_MAILER') == "phpmail") selected @endif>php mail</option>
                                        <option value="sendmail" @if(old('MAIL_MAILER') == "sendmail") selected @endif>sendmail</option>
                                    </select>
                                    @error('MAIL_MAILER')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                            </div>
                        </div>
                        
                        <div id="onlySmtp">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="mail_host">@lang('settings.mail_host'):</label>
                                        <input name="MAIL_HOST" value="{{ old('MAIL_HOST') ?: 'localhost' }}" type="text" class="form-control{{ $errors->has('MAIL_HOST') ? ' is-invalid' : '' }}" id="mail_host">
                                        @error('MAIL_HOST')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="mail_port">@lang('settings.mail_port'):</label>
                                        <input name="MAIL_PORT" value="{{ old('MAIL_PORT')}}" type="number" class="form-control{{ $errors->has('MAIL_PORT') ? ' is-invalid' : '' }}" id="mail_port">
                                        @error('MAIL_PORT')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="mail_encryption">@lang('settings.mail_encryption'):</label>
                                        <select name="MAIL_ENCRYPTION" class="form-control{{ $errors->has('MAIL_ENCRYPTION') ? ' is-invalid' : '' }}" id="mail_encryption">
                                            <option value="null" @if(!old('MAIL_ENCRYPTION') || old('MAIL_ENCRYPTION') == "null") selected @endif>@lang('settings.no_encryption')</option>
                                            <option value="tls" @if(old('MAIL_ENCRYPTION') == "tls") selected @endif>TLS</option>
                                            <option value="ssl" @if(old('MAIL_ENCRYPTION') == "ssl") selected @endif>SSL</option>
                                        </select>
                                        @error('MAIL_ENCRYPTION')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                    </div>
                                </div>
                            </div>                           
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="mail_username">@lang('settings.mail_username'):</label>
                                        <input name="MAIL_USERNAME" value="{{ old('MAIL_USERNAME')}}" type="text" class="form-control{{ $errors->has('MAIL_USERNAME') ? ' is-invalid' : '' }}" id="mail_username">
                                        @error('MAIL_USERNAME')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="mail_password">@lang('settings.mail_password'):</label>
                                        <input name="MAIL_PASSWORD" value="{{ old('MAIL_PASSWORD')}}" type="text" class="form-control{{ $errors->has('MAIL_PASSWORD') ? ' is-invalid' : '' }}" id="mail_password">
                                        @error('MAIL_PASSWORD')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                    </div>      
                                </div>
                            </div>                                
                        </div>

                        <button type="submit" class="btn btn-primary">
                            @if($errors->any())
                                @lang('setup.try_again')
                            @else
                                @lang('setup.mail_configure')
                            @endif
                            <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>

@endsection