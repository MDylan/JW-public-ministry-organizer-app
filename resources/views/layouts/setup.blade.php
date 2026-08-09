<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="height:auto;">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" type="image/jpg" href="{{ asset('pmo-favicon.png') }}"/>
  <title>{{ __('app.title') }}</title>
  <link rel="stylesheet" href="{{ pwbs_asset('/plugins/fontawesome-free/css/all.min.css') }}">
  <link rel="stylesheet" href="{{ pwbs_asset('/dist/css/adminlte.min.css') }}">
  <link rel="stylesheet" href="{{ pwbs_asset('/css/style.css') }}">
  <link rel="stylesheet" href="{{ pwbs_asset('/plugins/toastr/toastr.min.css') }}">
  <link rel="stylesheet" href="{{ pwbs_asset('/css/public_style.css') }}">
  @yield('header_style')
  @livewireStyles
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container">
                <div class="row">
                    <div class="col-md-12 text-center text-bold">
                        <h2>@lang('app.title')</h2>
                    </div>
                </div>                
            </div>
        </div>
        <div class="content">
            <div class="container">
            <div class="row">
                <div class="col-md-12">
                    @yield('content')
                </div>
            </div>
            </div>
        </div>
    </div>
</div>
<!-- ./wrapper -->
<!-- REQUIRED SCRIPTS -->
<script src="{{ pwbs_asset('/plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ pwbs_asset('/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ pwbs_asset('/dist/js/adminlte.min.js') }}"></script>

@yield('footer_scripts')
@livewireScripts  
</body>
</html>