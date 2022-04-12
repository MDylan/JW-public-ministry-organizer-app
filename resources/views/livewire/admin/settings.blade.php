<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-md-8">
                <h1 class="m-0">
                    @lang('app.menu-settings')
                </div><!-- /.col -->
                <div class="col-md-4">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                    <li class="breadcrumb-item"><a href="#">{{ __('app.menu-admin') }}</a></li>
                    <li class="breadcrumb-item active">{{ __('app.menu-settings') }}</li>
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
                <div class="col-lg-6">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <div class="card-title">@lang('settings.languages.title')</div>
                        </div>
                        <div class="card-body">
                            <div class="form-row  align-items-end">
                                <div class="form-group col-md-9">
                                    <label for="default_lang">@lang('settings.languages.default')</label>
                                    <select wire:model.defer="state.default_language" class="form-control" id="default_lang">
                                        @if (isset($settings['languages']))
                                            @foreach (json_decode($settings['languages'], true) as $country_code => $value)
                                                <option value="{{ $country_code }}">{{ $value['name'] }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="form-group col-md-3 align-text-bottom">
                                    <button type="button" class="btn btn-primary" wire:click="languageSetDefault">
                                        <i class="fa fa-save mr-1"></i> @lang('Save')
                                    </button>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-3">
                                    <input wire:model.defer="state.languageAdd.country_code" type="text" class="form-control @error('country_code') is-invalid @enderror" placeholder="@lang('settings.languages.country_code')" />
                                    @error('country_code')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <input wire:model.defer="state.languageAdd.country_name" type="text" class="form-control @error('country_name') is-invalid @enderror" placeholder="@lang('settings.languages.country_name')" />
                                    @error('country_name')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                                <div class="col-3">
                                    <button type="button" class="btn btn-primary" wire:click="languageAdd">
                                        <i class="fa fa-plus mr-1"></i>
                                            @lang('Add')                                        
                                </div>
                            </div>
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
                                                <td class="col-4">
                                                    @if ($country_code != $settings['default_language'])
                                                        <div class="btn-group" role="group" aria-label="">
                                                            @if($value['visible'])
                                                                <button type="button" class="btn btn-success btn-sm" wire:click="languageVisibility('{{ $country_code }}')" data-toggle="tooltip" data-placement="bottom" title="@lang('settings.languages.visibility.show')">
                                                                    <i class="fas fa-eye mr-1"></i>
                                                                </button>
                                                            @else
                                                                <button type="button" class="btn btn-warning btn-sm" wire:click="languageVisibility('{{ $country_code }}')"  data-toggle="tooltip" data-placement="bottom" title="@lang('settings.languages.visibility.admin')">
                                                                    <i class="fas fa-eye-slash mr-1"></i>
                                                                </button>
                                                            @endif
                                                        <button type="button" class="btn btn-danger btn-sm" wire:click="languageRemoveConfirmation('{{ $country_code }}')">
                                                            <i class="fa fa-trash mr-1"></i>
                                                                @lang('Remove')
                                                        </button>
                                                    </div>
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
                                @lang('settings.languages.lang_help')
                                <a href="/languages" class="btn btn-info" target="_blank">
                                    <i class="fa fa-arrow-right mr-1"></i>
                                    @lang('settings.languages.start_translation')
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-primary card-outline">
                        <div class="card-body p-1">
                            <div class="accordion" id="accordion">
                                <div class="card card-primary">
                                    <div class="card-header" id="mainSettingsHeader">
                                        <div class="card-title w-100">
                                            <a class="d-block w-100 collapsed" href="#mainSettings" role="button"
                                                data-toggle="collapse" data-target="#mainSettings" aria-expanded="true" aria-controls="mainSettings">
                                                <i class="fas fa-cogs mr-1"></i>
                                                @lang('settings.main.title')
                                            </a>
                                        </div>
                                    </div>
                                    <div wire:ignore.self id="mainSettings" class="collapse" aria-labelledby="mainSettingsHeader" data-parent="#accordion">
                                        <div class="card-body">                                            
                                            @lang('settings.main.info')
                                            <div class="form-group">
                                                <label for="app_name">@lang('settings.app_name'):</label>
                                                <input wire:model.defer="state.env.APP_NAME" type="text" class="form-control" id="app_name">
                                            </div>
                                            <div class="form-group">
                                                <label for="app_url">@lang('settings.app_url'):</label>
                                                <input wire:model.defer="state.env.APP_URL" type="text" class="form-control" id="app_url">
                                            </div>
                                            <div class="form-group">
                                                @php
                                                    $tzlist = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
                                                @endphp
                                                <label for="timezone">@lang('settings.timezone'):</label>
                                                <select wire:model.defer="state.env.TIMEZONE" class="form-control" id="timezone">
                                                    @foreach ($tzlist as $tz)
                                                        <option value="{{ $tz }}">{{ $tz }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card card-primary">
                                    <div class="card-header" id="mailSettingsHeader">
                                        <div class="card-title w-100">
                                            <a class="d-block w-100 collapsed" href="#mailSettings" role="button"
                                                data-toggle="collapse" data-target="#mailSettings" aria-expanded="true" aria-controls="mailSettings">
                                                <i class="far fa-envelope mr-1"></i>
                                                @lang('settings.mail')
                                            </a>
                                        </div>
                                    </div>
                                    <div wire:ignore.self id="mailSettings" class="collapse" aria-labelledby="mailSettingsHeader" data-parent="#accordion">
                                        <div class="card-body">                                            
                                            @lang('settings.mail_info')
                                            <div class="form-group">
                                                <label for="mail_from_address">@lang('settings.mail_from_address'):</label>
                                                <input wire:model.lazy="state.env.MAIL_FROM_ADDRESS" type="email" class="form-control" id="mail_from_address">
                                            </div>

                                            <div class="form-group">
                                                <label for="mail_mailer">@lang('settings.mail_mailer'):</label>
                                                <select wire:model="state.env.MAIL_MAILER" class="form-control" id="mail_mailer">
                                                    <option value="smtp">smtp</option>
                                                    <option value="phpmail">php mail</option>
                                                    <option value="sendmail">sendmail</option>
                                                </select>
                                            </div>
                                            
                                            @if($state['env']['MAIL_MAILER'] == "smtp")
                                                <div class="form-group">
                                                    <label for="mail_host">@lang('settings.mail_host'):</label>
                                                    <input wire:model.lazy="state.env.MAIL_HOST" type="text" class="form-control" id="mail_host">
                                                </div>
                                                <div class="form-group">
                                                    <label for="mail_port">@lang('settings.mail_port'):</label>
                                                    <input wire:model.lazy="state.env.MAIL_PORT" type="number" class="form-control" id="mail_port">
                                                </div>
                                                <div class="form-group">
                                                    <label for="mail_encryption">@lang('settings.mail_encryption'):</label>
                                                    <select wire:model.lazy="state.env.MAIL_ENCRYPTION" class="form-control" id="mail_encryption">
                                                        <option value="null">@lang('settings.no_encryption')</option>
                                                        <option value="tls">TLS</option>
                                                        <option value="ssl">SSL</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="mail_username">@lang('settings.mail_username'):</label>
                                                    <input wire:model.lazy="state.env.MAIL_USERNAME" type="text" class="form-control" id="mail_username">
                                                </div>
                                                <div class="form-group">
                                                    <label for="mail_password">@lang('settings.mail_password'):</label>
                                                    <input wire:model.lazy="state.env.MAIL_PASSWORD" type="text" class="form-control" id="mail_password">
                                                </div>                                            
                                            @endif

                                            <button wire:click="testMail" wire:loading.attr="disabled" class="btn btn-outline-info">
                                                <i class="fas fa-shipping-fast mr-1"></i>
                                                @lang('settings.mail_test')
                                            </button>
                                            <div style="float:right;" wire:loading wire:target="testMail">
                                                <div class="la-ball-clip-rotate la-dark la-sm mr-2" style="float:left;">
                                                    <div></div>
                                                </div>
                                                @lang('settings.mail_test_on_progress')
                                            </div>
                                            @if ($this->mailtest === true)
                                                <div class="alert alert-success mt-2" role="alert">
                                                    <i class="far fa-check-circle mr-1"></i>
                                                    @lang('settings.mail_test_success')
                                                </div>
                                            @elseif(strlen($this->mailtest) > 1)
                                                <div class="alert alert-danger mt-2" role="alert">
                                                    <h4><i class="fas fa-exclamation mr-1"></i>
                                                    @lang('settings.mail_test_error')</h4>
                                                    {{ $this->mailtest }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="card card-primary">
                                    <div class="card-header" id="otherSettingsHeader">
                                        <div class="card-title w-100">
                                            <a class="d-block w-100 collapsed" role="button"
                                                 data-target="#otherSettings" data-toggle="collapse" href="#otherSettings" aria-expanded="true">                                                 
                                                 <i class="fas fa-tasks mr-1"></i>
                                                 @lang('settings.others.title')
                                            </a>
                                        </div>                            
                                    </div>
                                    <div wire:ignore.self id="otherSettings" class="collapse" aria-labelledby="otherSettingsHeader" data-parent="#accordion">
                                        <div class="card-body">
                                            <div wire:ignore>
                                                @foreach ($this->others as $key => $value)
                                                    <div class="row mb-1">
                                                        <div class="col-md-2 pt-1 d-flex justify-content-end">
                                                            <input type="checkbox" data-key="{{ $key }}" @if($state['others'][$key] == 1) checked="" @endif data-bootstrap-switch="" data-off-color="danger" data-on-color="success">
                                                        </div>
                                                        <div class="col-md-10 pt-1">
                                                            {{ __('settings.others.'.$key) }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @if($state['others']['use_recaptcha'])
                                                <div class="alert alert-secondary">
                                                    <a href="https://g.co/recaptcha/v3" target="_blank">Google reCaptcha <i class="fas fa-external-link-alt"></i></a>
                                                    <div class="form-group">
                                                        <label for="site_key">SITE KEY:</label>
                                                        <input wire:model.ignore="state.recaptcha.site_key" type="text" class="form-control" id="site_key">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="secret_key">SECRET KEY:</label>
                                                        <input  wire:model.ignore="state.recaptcha.secret_key" type="text" class="form-control" id="secret_key">
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>                                    
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="button" class="btn btn-primary" wire:click="saveOthers">
                                <i class="fa fa-save mr-1"></i>
                                @lang('app.saveChanges')
                            </button>
                        </div>
                    </div>
                    

                    <div class="card card-primary card-outline collapsed-card">
                        <div class="card-header">
                            <div class="card-title">@lang('settings.run.title')</div>
                            <div class="card-tools" wire:ignore>
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body" wire:ignore>
                            <button type="button" class="btn btn-primary" wire:click="run('optimize')" wire:loading.attr="disabled">
                                <i class="fa fa-play mr-1"></i>
                                @lang('settings.run.optimize'): optimize:clear
                            </button>
                            <div class="w-100 py-2"></div>
                            <button type="button" class="btn btn-primary" wire:click="run('cache_clear')" wire:loading.attr="disabled">
                                <i class="fa fa-play mr-1"></i>
                                @lang('settings.run.optimize'): cache:clear
                            </button>
                            <div class="w-100 py-2"></div>
                            <button type="button" class="btn btn-primary" wire:click="run('view_clear')" wire:loading.attr="disabled">
                                <i class="fa fa-play mr-1"></i>
                                @lang('settings.run.optimize'): view:clear
                            </button>
                            <div class="w-100 py-2"></div>
                            <button type="button" class="btn btn-primary" wire:click="run('migrate')" wire:loading.attr="disabled">
                                <i class="fa fa-play mr-1"></i>
                                @lang('settings.run.optimize'): migrate
                            </button>
                            <div wire:loading wire:target="run">
                                <div class="la-ball-clip-rotate la-dark la-sm mr-2" style="float:left;">
                                    <div></div>
                                </div>
                                @lang('event.please_wait')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @section('footer_scripts')
    <script src="{{ asset('plugins/bootstrap-switch/js/bootstrap-switch.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.collapse').collapse();

            window.addEventListener('show-languageRemove-confirmation', event => {
                Swal.fire({
                    title: '@lang('settings.languages.confirmDelete.question')' + event.detail.lang,
                    text: '@lang('settings.languages.confirmDelete.message')',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '@lang('Yes')',
                    cancelButtonText: '@lang('Cancel')'
                }).then((result) => {
                if (result.isConfirmed) {
                    Livewire.emit('languageRemoveConfirmed');
                }
                })
            });
            $("input[data-bootstrap-switch]").each(function(){
                $(this).bootstrapSwitch({
                    'onText' : '@lang('app.on')',
                    'offText' : '@lang('app.off')',
                    'size' : 'normal',
                    'onSwitchChange' : function(event, state) {
                        var key = $(this).data('key');
                        @this.set('state.others.' + key, state);
                    }
                });
            })

        });
    </script>
    
    @endsection
</div>
