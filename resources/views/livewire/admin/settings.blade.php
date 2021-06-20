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
                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            @lang('settings.languages.title')
                        </div>
                        <div class="card-body">
                            <div class="form-row  align-items-end">
                                <div class="form-group col-md-9">
                                    <label for="default_lang">@lang('settings.languages.default')</label>
                                    <select wire:model.defer="state.default_language" class="form-control" id="default_lang">
                                        @if (isset($settings['languages']))
                                            @foreach (json_decode($settings['languages']) as $country_code => $country_name)
                                                <option value="{{ $country_code }}">{{ $country_name }}</option>
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
                                        <th colspan="2">@lang('settings.languages.country_name')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if (isset($settings['languages']))
                                        @foreach (json_decode($settings['languages']) as $country_code => $country_name)
                                            <tr>
                                                <td class="col-3 pt-2">
                                                    {{ $country_code }}
                                                </td>
                                                <td class="pt-2">
                                                    {{ $country_name }}
                                                </td>
                                                <td class="col-3">
                                                    @if ($country_code != $settings['default_language'])
                                                        <button type="button" class="btn btn-danger btn-sm" wire:click="languageRemoveConfirmation('{{ $country_code }}')">
                                                            <i class="fa fa-trash mr-1"></i>
                                                                @lang('Remove')
                                                        </button>
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @section('footer_scripts')
    <script>
        $(document).ready(function() {
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
        });
    </script>
    @endsection
</div>
