@extends('layouts.setup')

@section('content')

    <div class="row justify-content-center">
        <div class="col-12 col-md-8">

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-7 align-middle">
                            <h5 class="mt-2 m-0">@lang('setup.welcome')</h5>
                        </div>
                        <div class="col-md-5">
                            <form action="" method="GET" class="form-inline">
                            <label for="langselector" class="mr-2">
                                @lang('app.choose_language'):
                            </label>
                            <select name="lang" class="form-control" id="langSelector" onchange="this.form.submit();">
                                @foreach ($languages as $lang)
                                    <option value="{{$lang}}" @if ($lang == app()->getLocale() ) selected @endif>@lang('languages.'.$lang)</option>
                                @endforeach
                            </select>
                            </form>
                        </div>
                    </div>
                    
                </div>
                <div class="card-body">

                    <div class="progress progress-sm active mb-3">
                        <div class="progress-bar bg-success progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
                        <span class="sr-only">0% Complete</span>
                        </div>
                    </div>

                    <p>@lang('setup.intro_info')</p>

                    <ol>
                        <li>@lang('setup.intro.step1')</li>
                        <li>@lang('setup.intro.step2')</li>
                        <li>@lang('setup.intro.step3')</li>
                        <li>@lang('setup.intro.step4')</li>
                        <li>@lang('setup.intro.step5')</li>
                    </ol>

                    @if ($unlocked)
                        <a href="{{ route('setup.requirements') }}" class="btn btn-primary">
                            @lang('setup.check_requirements')
                            <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    @else
                        {{-- Unlocking the installer. The code lives on the
                             server, in the storage/app/installer-token.txt
                             file, so only someone with filesystem access can
                             enter it. This is the only check that makes
                             sense at this stage: there is no user yet. --}}
                        <hr>
                        <h6>@lang('setup.token.title')</h6>
                        <p class="text-muted">{!! __('setup.token.help') !!}</p>

                        @if (session('status'))
                            <div class="alert alert-warning">{{ session('status') }}</div>
                        @endif

                        <form action="{{ route('setup.unlock') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="installerToken">@lang('setup.token.label')</label>
                                <input type="text" name="token" id="installerToken"
                                       class="form-control @error('token') is-invalid @enderror"
                                       value="{{ old('token') }}" autocomplete="off" required>
                                @error('token')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">
                                @lang('setup.token.unlock')
                                <i class="fas fa-arrow-right ml-1"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>

        </div>
    </div>

@endsection