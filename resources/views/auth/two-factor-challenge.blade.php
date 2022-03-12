@extends('public')

@section('title')@lang('user.login')@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 align-self-center">
        <!-- /.login-logo -->
        <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="/" class="h1">{{__('app.title')}}</a>
        </div>
        <div class="card-body">
            <div id="haveCode">
                <form action="/two-factor-challenge" method="POST">
                    @csrf
                    <div class="form-group mb-3">
                        <label for="name">
                            <i class="fas fa-mobile-alt mr-1"></i>
                            @lang('user.two_factor.add_code')</label>
                        <input type="number" class="form-control @error('code') is-invalid @enderror" name="code" placeholder="" autocomplete="off">
                        @error('code')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                    </div>     
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt mr-1"></i>
                        {{__('user.login')}}
                    </button>
                </form>
                <button class="btn btn-secondary mt-3" onclick="showRevoveryPanel()">
                    <i class="fas fa-key mr-1"></i>
                    @lang('user.two_factor.no_device')
                </button>
            </div>

            <div id="noCode" style="display: none;" class="alert alert-light">
                <form action="/two-factor-challenge" method="POST">
                    @csrf
                    <div class="form-group mb-3">
                        <label for="name">
                            <i class="fas fa-mobile-alt mr-1"></i>
                            @lang('user.two_factor.add_recovery')</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" name="recovery_code" placeholder="" autocomplete="off">
                        @error('recovery_code')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                    </div>     
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt mr-1"></i>
                        {{__('user.login')}}
                    </button>
                </form>
                <button class="btn btn-secondary mt-3" onclick="showCodePanel()">
                    <i class="fas fa-key mr-1"></i>
                    @lang('user.two_factor.have_device')
                </button>
            </div>
            @if ($errors->any())
                <div class="alert alert-danger mt-4">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        </div>
        <!-- /.card -->
    </div>
    <!-- /.login-box -->
</div>
@endsection

@section('footer_scripts')

<script>
    function showRevoveryPanel() {
        $("#noCode").show();
        $("#haveCode").hide();
    }
    function showCodePanel() {
        $("#noCode").hide();
        $("#haveCode").show();
    }
</script>

@endsection