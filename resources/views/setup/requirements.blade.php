@extends('layouts.setup')

@section('content')

    <div class="row justify-content-center">
        <div class="col-12 col-md-8">

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h5 class="m-0">
                    @lang('setup.check_requirements')
                    </h5>
                </div>
                <div class="card-body">

                    <div class="progress progress-sm active mb-3">
                        <div class="progress-bar bg-success progress-bar-striped" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100" style="width: 20%">
                        <span class="sr-only">20% Complete</span>
                        </div>
                    </div>

                    <ul>
                        @foreach($results as $key => $successful)
                            <li>
                                @lang('setup.requirements.' . $key)
                                @if($successful)
                                    <i class="fas fa-check text-success"></i>
                                @else
                                    <i class="fas fa-exclamation text-danger"></i>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ route('setup.basics') }}" class="btn {{ $success ? 'btn-primary' : ' btn-danger disabled' }}">
                        @lang('setup.continue')
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>

                </div>
            </div>

        </div>
    </div>

@endsection