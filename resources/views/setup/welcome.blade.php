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

                    <a href="{{ route('setup.requirements') }}" class="btn btn-primary">
                        @lang('setup.check_requirements')
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>

@endsection