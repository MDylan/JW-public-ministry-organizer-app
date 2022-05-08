<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="height:auto;">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" type="image/jpg" href="{{ asset('favicon.png') }}"/>
  <title>{{ __('app.title') }}</title>
  {!! Packer::css('/plugins/fontawesome-free/css/all.min.css', '/plugins/fontawesome-free/css/cache_fontawesome.css') !!}
  {!! Packer::css('/dist/css/adminlte.min.css', '/dist/css/cache_adminlte.min.css') !!}
  {!! Packer::css([
    'css/style.css',
    'plugins/toastr/toastr.min.css',
    'css/public_style.css'
  ], 
  '/storage/cache/css/all_style.css') !!}
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
{!! Packer::js('/plugins/jquery/jquery.min.js', '/storage/cache/js/jquery.js') !!}  
{!! Packer::js('/plugins/bootstrap/js/bootstrap.bundle.min.js', '/plugins/bootstrap/js/cache_bootstrap.js') !!}
{!! Packer::js('/dist/js/adminlte.min.js', '/dist/js/cache_adminlte.js') !!}

@yield('footer_scripts')
@livewireScripts  
</body>
</html>