@extends('layouts.setup')

@section('content')

    <div class="row justify-content-center">
        <div class="col-12 col-md-8">

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h5>@lang('setup.complete')</h5>
                </div>
                <div class="card-body">

                    <p><i class="fas fa-thumbs-up mr-1"></i>
                        @lang('setup.outro')</p>

                    <a href="{{ route('home.home') }}" class="btn btn-primary">
                        @lang('app.menu-home')
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>

@endsection