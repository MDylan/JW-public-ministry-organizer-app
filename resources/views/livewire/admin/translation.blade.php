<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-md-8">
                <h1 class="m-0">
                    @lang('app.menu-translation')
                </div><!-- /.col -->
                <div class="col-md-4">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                    <li class="breadcrumb-item">{{ __('app.menu-admin') }}</li>
                    <li class="breadcrumb-item active">{{ __('app.menu-translation') }}</li>
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
                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <div class="card-title">@lang('settings.languages.title')</div>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>@lang('settings.languages.country_code')</th>
                                        <th colspan="3">@lang('settings.languages.country_name')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if (isset($settings['languages']))
                                        @foreach (json_decode($settings['languages'], true) as $country_code => $value)
                                            <tr>
                                                <td class="col-2 pt-2">
                                                    {{ $country_code }}
                                                </td>
                                                <td class="pt-2">
                                                    {{ $value['name'] }}
                                                </td>
                                                <td class="pt-2">
                                                    <a href="/languages/{{ $country_code }}/translations" target="_blank">
                                                        <i class="fa fa-arrow-right mr-1"></i>
                                                        @lang('settings.languages.translate')
                                                    </a>
                                                </td>
                                                <td class="col-5">
                                                    @if($value['visible'])
                                                        <i class="fas fa-eye mr-1"></i>
                                                        @lang('settings.languages.visibility.show')
                                                    @else
                                                        <i class="fas fa-eye-slash mr-1"></i>
                                                        @lang('settings.languages.visibility.admin')
                                                     @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="3">@lang('settings.languages.empty')</td>
                                        </tr>
                                    @endif                                    
                                </tbody>
                            </table>
                            <div class="alert alert-light mt-2" role="alert">
                                @lang('settings.languages.translator_help')
                                <a href="/languages" class="btn btn-info" target="_blank">
                                    <i class="fa fa-arrow-right mr-1"></i>
                                    @lang('settings.languages.start_translation')
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
