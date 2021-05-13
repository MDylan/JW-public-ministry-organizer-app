<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('app.title') }}</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
  <!-- Theme style -->
  <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
  @livewireStyles
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

  <!-- Navbar -->
  {{-- @include('layouts.partials.navbar') --}}
  @livewire('partials.nav-bar')
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  {{-- @include('layouts.partials.aside') --}}
  @livewire('partials.side-menu')

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    @if (!auth()->user()->email_verified_at)
      
    
    <div class="callout callout-danger m-2">
      <h5>{{__('Verify Email Address')}}</h5>

      <p>{!! __('app.verifyEmail', ['url' => '/email/verify']) !!}</p>
    </div>
    @endif
    {{ $slot }}
  </div>
  <!-- /.content-wrapper -->

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
    <div class="p-3">
      <h5>Title</h5>
      <p>Sidebar content</p>
    </div>
  </aside>
  <!-- /.control-sidebar -->

  <!-- Main Footer -->
  @include('layouts.partials.footer')
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
<!-- Bootstrap 4 -->
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<!-- AdminLTE App -->
<script src="{{ asset('dist/js/adminlte.min.js') }}"></script>
<script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
<script>
  $(document).ready(function() {
    toastr.options = {
      "progressBar": true,
      "positionClass": "toast-bottom-right",
    }
    window.addEventListener('hide-form', event => {
        $('#form').modal('hide');
        toastr.success(event.detail.message, '{{__('app.saved')}}');
    });
    window.addEventListener('show-form', event => {
        $('#form').modal('show');
    });
    window.addEventListener('show-delete-modal', event => {
        $('#confirmationModal').modal('show');
    });
    window.addEventListener('hide-delete-modal', event => {
        $('#confirmationModal').modal('hide');
        if(event.detail.message)
          toastr.success(event.detail.message, '{{__('app.saved')}}');
        if(event.detail.errorMessage)
          toastr.error(event.detail.errorMessage, '{{__('app.errorWhileSaved')}}');
    });
    window.addEventListener('success', event => {
        toastr.success(event.detail.message, '{{__('app.saved')}}');
    });
    window.addEventListener('error', event => {
        toastr.error(event.detail.message, '{{__('app.errorWhileSaved')}}');
    });
    
  });
</script>
@yield('footer_scripts')
@livewireScripts
</body>
</html>