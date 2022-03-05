<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="height:auto;">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" type="image/jpg" href="{{ asset('favicon.png') }}"/>
  <title>{{ __('app.title') }}</title>
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
  <!-- Theme style -->
  <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}?ver={{ filemtime(public_path('css/style.css')) }}">
  <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/public_style.css') }}">
  @yield('header_style')
  @livewireStyles
</head>
<body class="sidebar-mini" style="height:auto;">
  <div class="wrapper">
    <!-- Navbar -->
    @livewire('partials.nav-bar')
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    @livewire('partials.side-menu')

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
      @if (!auth()->user()->email_verified_at)
        <div class="callout callout-danger m-2">
          <h5>{{__('Verify Email Address')}}</h5>
          <p>{!! __('app.verifyEmail', ['url' => '/email/verify']) !!}</p>
        </div>
      @endif
      @can('is-admin')
        <x-updateNotification></x-updateNotification>
      @endcan
      {{ $slot }}
    </div>
    @include('cookie-consent::index')
    <!-- /.content-wrapper -->
    <!-- Main Footer -->
    @include('layouts.partials.footer')
    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark" id="control_sidebar">
      <!-- Control sidebar content goes here -->
      <div class="p-2">
        <h5>@lang('event.eventsBar.title')</h5>
        @livewire('partials.events-bar', [], key('eventsBar'))
      </div>
    </aside>
    <!-- /.control-sidebar -->
    @livewire('events.modal', ['groupId' => 0], key('eventsmodal'))  
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
  <script src="{{ asset('plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>
  <script src="{{ asset('js/custom.js') }}?ver={{ filemtime(public_path('js/custom.js')) }}"></script>
  <script src="{{ asset('js/modal.js') }}?ver={{ filemtime(public_path('js/modal.js')) }}"></script>
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
       window.addEventListener('success', event => {
          toastr.success(event.detail.message, '{{__('app.saved')}}');
      });
      window.addEventListener('error', event => {
          toastr.error(event.detail.message, '{{__('app.errorWhileSaved')}}');
      });
      window.addEventListener('sweet-error', event => {
        Swal.fire({
          icon: 'error',
          title: '' + event.detail.title + '',
          text: ''+ event.detail.message + ''
        })
      });

      window.addEventListener('show-eventDelete-confirmation', event => {
        Swal.fire({
          title: '@lang('event.confirmDelete.question')',
          text: '@lang('event.confirmDelete.message')',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: '@lang('Yes')',
          cancelButtonText: '@lang('Cancel')'
        }).then((result) => {
          if (result.isConfirmed) {
              Livewire.emit('deleteConfirmed');
          }
        })
      });
      window.addEventListener('show-deletion-confirmation', event => {
        Swal.fire({
          title: event.detail.title,
          text: event.detail.text,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: '@lang('Yes')',
          cancelButtonText: '@lang('Cancel')'
        }).then((result) => {
          if (result.isConfirmed) {
              Livewire.emit(event.detail.emit);
          }
        })
      });
      @if(Session::has('message'))
        toastr.success('{{Session::get('message')}}', '{{__('app.saved')}}');
      @endif
    });
  </script>  

  @yield('footer_scripts')
  <!-- Alpine v3 -->
  {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}
  @livewireScripts  
  {{-- @livewire('livewire-ui-modal') --}}
  {{-- @livewire('modal.modal') --}}
</body>
</html>