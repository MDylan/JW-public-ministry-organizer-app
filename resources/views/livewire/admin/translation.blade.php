<div>
    @section('title', __('translation.title'))

    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">@lang('translation.title')</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('home.home') }}">@lang('app.menu-home')</a></li>
                        <li class="breadcrumb-item active">@lang('translation.title')</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">@lang('translation.title')</h3>
                </div>

                <div class="card-body">

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="sourceLocale">@lang('translation.source_locale')</label>
                            <select wire:model.live="sourceLocale" id="sourceLocale" class="form-control">
                                @foreach ($locales as $code => $locale)
                                    <option value="{{ $code }}">{{ $locale['name'] }} ({{ $code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="targetLocale">@lang('translation.target_locale')</label>
                            <select wire:model.live="targetLocale" id="targetLocale" class="form-control">
                                @foreach ($locales as $code => $locale)
                                    <option value="{{ $code }}">{{ $locale['name'] }} ({{ $code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="group">@lang('translation.group')</label>
                            <select wire:model.live="group" id="group" class="form-control">
                                @foreach ($groups as $availableGroup)
                                    <option value="{{ $availableGroup }}">
                                        {{ $availableGroup === 'json' ? __('translation.json_group') : $availableGroup }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-3">
                            <label for="search">@lang('translation.search')</label>
                            <div class="d-flex align-items-center border rounded bg-white pr-2">
                                <input wire:model.live.debounce.400ms="search" type="text" id="search"
                                       class="form-control border-0" placeholder="@lang('translation.search')" />
                                <div wire:loading.delay wire:target="search">
                                    <div class="la-ball-clip-rotate la-dark la-sm"><div></div></div>
                                </div>
                                @if (Str::length($search) > 0)
                                    <a href="javascript:void(0);" wire:click="clearSearch"><i class="fa fa-times-circle p-2"></i></a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div class="custom-control custom-checkbox">
                            <input wire:model.live="onlyMissing" type="checkbox" class="custom-control-input" id="onlyMissing">
                            <label class="custom-control-label" for="onlyMissing">@lang('translation.only_missing')</label>
                        </div>

                        @if ($missingCount > 0)
                            <span class="badge badge-warning">
                                @lang('translation.missing_count', ['count' => $missingCount])
                            </span>
                        @endif
                    </div>

                    <hr>

                    <div class="table-responsive" wire:loading.class="text-muted">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th style="width: 25%">@lang('translation.key')</th>
                                    <th style="width: 30%">{{ $sourceLocale }}</th>
                                    <th>{{ $targetLocale }}</th>
                                    <th style="width: 1%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $index => $row)
                                    <tr class="{{ $row['value'] === '' ? 'table-warning' : '' }}">
                                        <td class="text-monospace small align-middle">{{ $row['key'] }}</td>
                                        <td class="small align-middle">{{ $row['source'] }}</td>
                                        <td>
                                            <textarea wire:model="rows.{{ $index }}.value" rows="1"
                                                      class="form-control form-control-sm"></textarea>
                                        </td>
                                        <td class="align-middle">
                                            <button type="button" class="btn btn-sm btn-primary"
                                                    wire:click="save({{ $index }})"
                                                    title="@lang('translation.save')">
                                                <i class="fa fa-save"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-bold p-3">
                                            <i class="fa fa-exclamation-circle mr-1"></i>@lang('translation.nothing_found')
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-primary" wire:click="saveAll">
                            <i class="fa fa-save mr-1"></i>@lang('translation.save_all')
                        </button>
                        <div>{{ $paginator->links() }}</div>
                    </div>

                </div>
            </div>

            <div class="card card-secondary card-outline">
                <div class="card-header">
                    <h3 class="card-title">@lang('translation.add_key')</h3>
                </div>
                <div class="card-body">
                    <form wire:submit="addKey">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="newKey">@lang('translation.key')</label>
                                <input type="text" wire:model="state.newKey" id="newKey"
                                       class="form-control @error('newKey') is-invalid @enderror">
                                @error('newKey')
                                    <div class="invalid-feedback" role="alert">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">@lang('translation.add_key_help')</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="newValue">@lang('translation.value')</label>
                                <input type="text" wire:model="state.newValue" id="newValue"
                                       class="form-control @error('newValue') is-invalid @enderror">
                                @error('newValue')
                                    <div class="invalid-feedback" role="alert">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-secondary btn-block">
                                    <i class="fa fa-plus mr-1"></i>@lang('translation.add_key')
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-light border">
                <p class="mb-1"><i class="fa fa-info-circle mr-1"></i>@lang('translation.registry_help')</p>
                <p class="mb-1"><i class="fa fa-info-circle mr-1"></i>@lang('translation.unsaved_warning')</p>
                <p class="mb-0"><i class="fa fa-info-circle mr-1"></i>@lang('translation.comment_warning')</p>
            </div>

        </div>
    </div>
</div>
