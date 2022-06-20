<x-admin-layout>
@section('title')
@lang('app.authentication_needed')
@endsection
<div class="content my-auto">
    <div class="d-flex align-items-center justify-content-center text-center" style="height:400px;">
        <div class="card card-outline card-warning" style="width: 400px;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-key mr-1"></i>
                    @lang('app.authentication_needed')</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        @lang('app.authentication_info', ['number' => round(config('auth.password_timeout') / 60 / 60)])
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12 justify-content-center text-center">
                        <form class="input-group has-validation" method="POST" action="{{ route('password.confirm') }}">
                            @csrf
                            <div class="input-group-prepend">
                                <span class="input-group-text">@lang('Password'):</span>
                            </div>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" />
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-unlock-alt mr-1"></i>
                                    @lang('app.send')</button>
                            </div>
                            @error('password')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                            
                          </form>
                    </div>
                </div>
            </div>
            <!-- /.card-body -->
          </div>
    </div>
    
</div>
</x-admin-layout>