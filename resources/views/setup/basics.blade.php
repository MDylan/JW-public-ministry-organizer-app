@extends('layouts.setup')

@section('content')

    <div class="row justify-content-center">
        <div class="col-12 col-md-8">

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h5>
                        @lang('setup.intro.step2')
                    </h5>
                </div>
                <div class="card-body">

                    <div class="progress progress-sm active mb-3">
                        <div class="progress-bar bg-success progress-bar-striped" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 40%">
                        <span class="sr-only">40% Complete</span>
                        </div>
                    </div>

                    @include('setup.alert')

                    <form action="{{ route('setup.save-basics') }}" method="POST">
                        @csrf

                        <div class="form-group">
                            <label for="app_name">@lang('settings.app_name'):</label>
                            <input name="APP_NAME" value="{{ old('APP_NAME') ?: getenv('APP_NAME') }}" type="text" class="form-control" id="app_name">
                        </div>
                        <div class="form-group">
                            <label for="app_url">@lang('settings.app_url'):</label>
                            <input name="APP_URL" value="{{ old('APP_URL') ?: url("/") }}" type="text" class="form-control" id="app_url">
                        </div>
                        <div class="form-group">
                            <label for="timezone">@lang('settings.languages.default'):</label>
                            <select name="APP_LANG" class="form-control" id="language">
                                @foreach ($languages as $lang)
                                    <option value="{{$lang}}" @if ($lang == app()->getLocale() ) selected @endif>@lang('languages.'.$lang)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            @php
                                $tzlist = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
                            @endphp
                            <label for="timezone">@lang('settings.timezone'):</label>
                            <select name="TIMEZONE" class="form-control" id="timezone">
                                @foreach ($tzlist as $tz)
                                    <option value="{{ $tz }}" @if($tz == $timezone) selected @endif >{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            @if($errors->any())
                                @lang('setup.try_again')
                            @else
                                @lang('app.save')
                            @endif
                            <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection