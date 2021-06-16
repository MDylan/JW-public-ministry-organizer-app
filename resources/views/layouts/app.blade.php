<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="height:auto;">
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
  @yield('header_style')
  @livewireStyles
</head>
<body class="sidebar-mini" style="height:auto;">
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
    <!-- Main Footer -->
    @include('layouts.partials.footer')
    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
      <!-- Control sidebar content goes here -->
      <div class="p-3">
        <h5>@lang('event.eventsBar.title')</h5>
        @livewire('partials.events-bar', [], key('eventsBar'))
      </div>
    </aside>
    <!-- /.control-sidebar -->
    <div id="sidebar-overlay"></div>

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
      window.addEventListener('show-reject-confirmation', event => {
        Swal.fire({
          title: '@lang('group.reject_question')',
          text: '@lang('group.reject_message')',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: '@lang('Yes')',
          cancelButtonText: '@lang('Cancel')'
        }).then((result) => {
          if (result.isConfirmed) {
              Livewire.emit('rejectConfirmed');
          }
        })
      });
      window.addEventListener('sweet-error', event => {
        Swal.fire({
          icon: 'error',
          title: '' + event.detail.title + '',
          text: ''+ event.detail.message + ''
        })
      });

      window.addEventListener('show-logout-confirmation', event => {
        Swal.fire({
          title: '@lang('group.logout.question')',
          text: '@lang('group.logout.message')',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: '@lang('Yes')',
          cancelButtonText: '@lang('Cancel')'
        }).then((result) => {
          if (result.isConfirmed) {
              Livewire.emit('logoutConfirmed');
          }
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
      @if(Session::has('message'))
        toastr.success('{{Session::get('message')}}', '{{__('app.saved')}}');
      @endif
    });
  </script>

  <script>
    var toggleSwitch = document.querySelector('.theme-switch input[type="checkbox"]');
    var currentTheme = localStorage.getItem('theme');
    var mainHeader = document.querySelector('.main-header');

    if (currentTheme) {
      if (currentTheme === 'dark') {
        if (!document.body.classList.contains('dark-mode')) {
          document.body.classList.add("dark-mode");
        }
        if (mainHeader.classList.contains('navbar-light')) {
          mainHeader.classList.add('navbar-dark');
          mainHeader.classList.remove('navbar-light');
        }
        console.log(toggleSwitch.checked);
        toggleSwitch.checked = true;
        console.log(toggleSwitch.checked);
      }
    }

    function switchTheme(e) {
      if (e.target.checked) {
        if (!document.body.classList.contains('dark-mode')) {
          document.body.classList.add("dark-mode");
        }
        if (mainHeader.classList.contains('navbar-light')) {
          mainHeader.classList.add('navbar-dark');
          mainHeader.classList.remove('navbar-light');
        }
        localStorage.setItem('theme', 'dark');
      } else {
        if (document.body.classList.contains('dark-mode')) {
          document.body.classList.remove("dark-mode");
        }
        if (mainHeader.classList.contains('navbar-dark')) {
          mainHeader.classList.add('navbar-light');
          mainHeader.classList.remove('navbar-dark');
        }
        localStorage.setItem('theme', 'light');
      }
    }

    toggleSwitch.addEventListener('change', switchTheme, false);
  </script>

  @yield('footer_scripts')
  @livewireScripts
</body>
</html>