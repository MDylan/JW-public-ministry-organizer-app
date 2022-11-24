<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="height:auto;">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" type="image/jpg" href="{{ asset('pmo-favicon.png') }}"/>
  <title>{{ __('app.title') }} | @yield('title')</title>
  {!! Packer::css('/plugins/fontawesome-free/css/all.min.css', '/plugins/fontawesome-free/css/cache_fontawesome.css') !!}
  {!! Packer::css('/dist/css/adminlte.min.css', '/dist/css/cache_adminlte.min.css') !!}
  {!! Packer::css([
    'css/style.css',
    'plugins/toastr/toastr.min.css',
    'css/public_style.css'
  ], 
  '/cache/css/all_style.css') !!}
  @yield('header_style')
  @livewireStyles
</head>
<body class="sidebar-mini @if(($_COOKIE['currentTheme'] ?? 'light') == 'dark') dark-mode @endif" style="height:auto;">
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
      @if (!auth()->user()->two_factor_confirmed && !is_null(auth()->user()->two_factor_secret) && !request()->routeIs('user.twofactorsettings'))
        <div class="alert alert-warning mx-2 my-2">
          <h5>@lang('user.two_factor.status_disabled')</h5>
          <p>@lang('user.two_factor.half_way') 
            <a class="btn btn-success text-decoration-none" href="{{ route('user.twofactorsettings') }}">
              <i class="fas fa-lock mr-1"></i>
              @lang('user.two_factor.title')</a>
            </p>
        </div>
      @endif
      @can('is-admin')
        <x-updateNotification></x-updateNotification>
      @endcan
      {{ $slot }}
    </div>
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
    {{-- @livewire('events.modal', ['groupId' => 0], key('eventsmodal'))   --}}
    <livewire:events.modal :groupId="0" :wire:key="events_modal">
  </div>
  <!-- ./wrapper -->
  <!-- REQUIRED SCRIPTS -->
  {!! Packer::js('/plugins/jquery/jquery.min.js', '/cache/js/jquery.js') !!}  
  {!! Packer::js('/plugins/bootstrap/js/bootstrap.bundle.min.js', '/plugins/bootstrap/js/cache_bootstrap.js') !!}
  {!! Packer::js('/dist/js/adminlte.min.js', '/dist/js/cache_adminlte.js') !!}
  {!! Packer::js('/plugins/toastr/toastr.min.js', '/plugins/toastr/cache_toastr.js') !!}
  {!! Packer::js('/plugins/sweetalert2/sweetalert2.all.min.js', '/plugins/sweetalert2/cache_sweetalert2.js') !!}
  {!! Packer::js([
    '/js/custom.js',
    '/js/modal.js',
  ], 
  '/cache/js/all.js') !!}
  <script>
    $(document).ready(function() {
      toastr.options = {
        "progressBar": true,
        "positionClass": "toast-bottom-right",
      }
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
  @livewireScripts  
  <script>
    document.addEventListener('livewire:load', () => {
        Livewire.onPageExpired(
          (response, message) => {
            res = confirm('{{ __('app.page_expired') }}');
            if(res == true) {
              location.reload();
            } else {
              return res;
            }
          }
        );
    })
  </script>
</body>
</html>