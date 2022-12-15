<x-admin-layout>
    <div>
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                <h1 class="m-0">{{__('user.two_factor.title')}}</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{__('app.menu-home')}}</a></li>
                    <li class="breadcrumb-item"><a href="{{route('user.profile')}}">{{__('app.profile')}}</a></li>
                    <li class="breadcrumb-item active">{{__('user.two_factor.title')}}</li>
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
                <!-- /.col-md-6 -->
                <div class="col-md-6">
                    <div class="pt-2 card card-primary card-outline">
                        <div class="card-header">
                        <h5 class="m-0">@lang('user.two_factor.title')</h5>
                        </div>
                        <div class="card-body">
                            <i class="fas fa-info-circle mr-1"></i> @lang('user.two_factor.help')

                            @if (session('status') == 'two-factor-authentication-disabled')
                                <div class="alert alert-success">
                                    @lang('user.two_factor.disabled')
                                </div>
                            @endif
                            <p class="mt-2">
                                {{-- 2FA confirmed, we show a 'disable' button to disable it --}}
                                @if(auth()->user()->two_factor_confirmed)
                                    <b>@lang('user.two_factor.status_enabled')</b>

                                    <form action="{{ route('two-factor.enable') }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                            <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-power-off mr-1"></i> @lang('user.two_factor.button_disable')</button>
                                    </form>
                                {{-- 2FA enabled but not yet confirmed, we show the QRcode and ask for confirmation --}}
                                @elseif(auth()->user()->two_factor_secret)
                                    <div class="alert alert-warning">
                                        <p><b>@lang('user.two_factor.please_confirm')</b></p> 
                                        <div class="my-2 text-center">
                                            <h5>@lang('user.two_factor.recommended_apps')</h5>
                                            <a class="btn btn-info text-decoration-none" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">
                                                <i class="fab fa-android mr-1"></i> Google Play
                                            </a>
                                            <a a class="btn btn-info text-decoration-none" href="https://apps.apple.com/us/app/microsoft-authenticator/id983156458" target="_blank">
                                                <i class="fab fa-app-store-ios mr-1"></i> App Store
                                            </a>
                                        </div>
                                        <div class="w-100 text-center py-1 mb-1">
                                            {!! auth()->user()->twoFactorQrCodeSvg(); !!}
                                        </div>
                                    
                                        <form action="{{route('two-factor.confirm')}}" method="post">
                                            @csrf
                                            <input class="form-control mb-1" name="code" required placeholder="@lang('user.two_factor.add_code')"/>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-mobile-alt mr-1"></i> @lang('user.two_factor.button_enable')
                                            </button>
                                        </form>

                                        <form action="{{ route('two-factor.enable') }}" class="mt-5" method="POST">
                                            @csrf
                                            @method('DELETE')
                                                <button type="submit" class="btn btn-warning mt-2">
                                                <i class="fas fa-power-off mr-1"></i> @lang('Cancel')</button>
                                        </form>
                                    </div>
                                @else
                                    <b>@lang('user.two_factor.status_disabled')</b>
                                    <form action="{{ route('two-factor.enable') }}" method="POST">
                                    @csrf
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-power-off mr-1"></i> @lang('Enable')</button>
                                    </form>
                                @endif
                            </p>
                            <a href="{{ route('user.profile') }}" class="btn btn-primary">
                                <i class="fas fa-arrow-circle-left mr-1"></i>
                                @lang('app.back')
                            </a>
                        </div>
                    </div>
                </div>
                @if (auth()->user()->two_factor_confirmed)
                    <div class="col-6">
                        <div class="pt-2 card card-primary card-outline">
                            <div class="card-header">
                                <h5 class="m-0">@lang('user.two_factor.recovery_codes')</h5>
                            </div>
                            <div class="card-body">
                                <p>                                    
                                    <b>@lang('user.two_factor.store_it')</b>
                                </p>                                    
                                <div class="row">
                                    <div class="col-6 border border-primary text-center rounded py-2">
                                        <samp id="two_factor_codes">
                                            @foreach (auth()->user()->recoveryCodes() as $code)
                                                {{ $code }}<br/>
                                            @endforeach
                                        </samp>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-primary" onclick="copyToClipboard('#two_factor_codes')">
                                            <i class="far fa-copy mr-1"></i>
                                            @lang('user.two_factor.copy_to_clipboard')
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-2">@lang('user.two_factor.scan')</p>
                                <div class="mb-2 text-center">
                                    <h4>@lang('user.two_factor.recommended_apps')</h4>
                                    <a class="btn btn-info text-decoration-none" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">
                                        <i class="fab fa-android mr-1"></i> Google Play
                                    </a>
                                    <a a class="btn btn-info text-decoration-none" href="https://apps.apple.com/us/app/microsoft-authenticator/id983156458" target="_blank">
                                        <i class="fab fa-app-store-ios mr-1"></i> App Store
                                    </a>
                                </div>
                                <div class="w-100 text-center py-1">
                                    {!! auth()->user()->twoFactorQrCodeSvg(); !!}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <!-- /.col-md-6 -->
            </div>
            <!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content -->
        @section('footer_scripts')
        <script>
            function copyToClipboard(element) {
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val($(element).text()).select();
                document.execCommand("copy");
                $temp.remove();
                toastr.success('{{__('user.two_factor.copy_success')}}', '{{__('user.two_factor.copy_to_clipboard')}}');
            }
        </script>
        @endsection
    </div>
</x-admin-layout>