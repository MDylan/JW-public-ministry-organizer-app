<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{ __('group.editGroup') }}</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{route('groups')}}">{{ __('app.menu-groups') }}</a></li>
                <li class="breadcrumb-item active">{{ __('group.addNew') }}</li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <form wire:submit.prevent="updateGroup">
                @csrf
                <div class="row">
                    {{-- @dump($future_changes) --}}
                    @if (isset($future_changes))
                        <div wire:ignore class="col-lg-12">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle mr-1"></i> @lang('group.update.in_progress', [
                                    'dateFrom' => $future_changes->change_date,
                                    'userName' => $future_changes->user->name
                                ])
                                <div class="w-100 py-2"></div>
                                @if(request()->input('show_future')) 
                                    <a title="@lang('app.delete')" href="" wire:click.prevent="confirmFutureChangesRemoval()" class="float-right btn btn-danger mr-1 mb-1">
                                        <i class="fas fa-trash mr-1"></i>
                                        @lang('group.update.in_progress_delete')
                                    </a>
                                @else
                                <a href="?show_future=1" class="btn btn-info">
                                    <i class="far fa-eye mr-1"></i>
                                    @lang('group.update.in_progress_show')
                                </a>
                                @endif
                                <div class="w-100 py-2"></div>
                                <a wire:ignore href="{{ route('groups') }}" class="btn btn-primary mb-2 mb-md-0">
                                    <i class="fas fa-arrow-alt-circle-left mr-1"></i>
                                    @lang('app.back')
                                </a>
                            </div>

                        </div>
                    @endif
                    @if(!isset($future_changes) || request()->input('show_future'))
                    <div class="col-lg-6">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <div class="card-title">{{__('group.group_head')}}</div>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                      <i class="fas fa-minus"></i>
                                    </button>
                                  </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="row">
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="inputName">{{__('group.name')}}</label>
                                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="inputName" wire:model.defer="state.name" value="" placeholder="" />
                                                    @error('name')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="inputName">{{__('group.replyToAddress')}}</label>
                                                    <input type="email" class="form-control @error('replyTo') is-invalid @enderror" id="replyTo" wire:model.defer="state.replyTo" value="" placeholder=""  aria-describedby="replyToHelper" />
                                                    <small id="replyToHelper" class="form-text text-muted">
                                                        @lang('group.replyToHelper', ['defaultMail' => env('MAIL_FROM_ADDRESS')])
                                                    </small>
                                                    @error('replyTo')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row align-items-end">
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="max_extend_days">{{__('group.max_extend_days')}}</label>
                                                    <input type="number" class="form-control @error('max_extend_days') is-invalid @enderror" id="max_extend_days" wire:model.defer="state.max_extend_days" value="" placeholder="{{__('group.max_extend_days_placeholder')}}" />
                                                    @error('max_extend_days')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="need_approval">{{__('group.need_approval')}}</label>
                                                    <small id="approval_help" class="form-text text-muted">
                                                        @lang('group.need_approval_help')
                                                    </small>
                                                    <select class="form-control @error('need_approval') is-invalid @enderror" id="need_approval" wire:model="state.need_approval" aria-describedby="approval_help">
                                                        <option value="0">@lang('No')</option>
                                                        <option value="1">@lang('Yes')</option>
                                                    </select>
                                                    @error('need_approval')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>
                                            </div>
                                        </div>
                                        @if ($state['need_approval'] == 1)
                                            <div class="row alert alert-light">
                                                <div class="col-12">
                                                    <div class="form-group">
                                                        <label for="auto_approval">{{__('group.auto_approval')}}</label>
                                                        <small id="auto_approval_help" class="form-text text-muted">
                                                            @lang('group.auto_approval_help')
                                                        </small>
                                                        <select class="form-control @error('auto_approval') is-invalid @enderror" id="auto_approval" wire:model.defer="state.auto_approval" aria-describedby="auto_approval_help">
                                                            <option value="0">@lang('No')</option>
                                                            <option value="1">@lang('Yes')</option>
                                                        </select>
                                                        @error('auto_approval')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="form-group">
                                                        <label for="auto_back">{{__('group.auto_back')}}</label>
                                                        <small id="auto_back_help" class="form-text text-muted">
                                                            @lang('group.auto_back_help')
                                                        </small>
                                                        <select class="form-control @error('auto_back') is-invalid @enderror" id="auto_back" wire:model.defer="state.auto_back" aria-describedby="auto_back_help">
                                                            <option value="0">@lang('No')</option>
                                                            <option value="1">@lang('Yes')</option>
                                                        </select>
                                                        @error('auto_back')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        
                                        <div class="row">
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="min_publishers">{{__('group.min_publishers')}}</label>
                                                    <input type="number" class="form-control @error('min_publishers') is-invalid @enderror" id="min_publishers" wire:model.defer="state.min_publishers" value="" placeholder="{{__('group.min_publishers_placeholder')}}" />
                                                    @error('min_publishers')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="max_publishers">{{__('group.max_publishers')}}</label>
                                                    <input type="number" class="form-control @error('max_publishers') is-invalid @enderror" id="max_publishers" wire:model.defer="state.max_publishers" value="" placeholder="{{__('group.max_publishers_placeholder')}}" />
                                                    @error('max_publishers')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>  
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="min_time">{{__('group.min_time')}}</label>
                                                    <select wire:model="state.min_time" name="min_time" id="min_time" class="form-control @error('min_time') is-invalid @enderror">
                                                        @foreach ($min_time_options as $field => $translate) 
                                                            <option value="{{$translate}}">{{__('group.min_time_options.'.$translate)}}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('min_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="max_time">{{__('group.max_time')}}</label>
                                                    <select name="max_time" id="max_time" class="form-control @error('max_time') is-invalid @enderror" wire:model.defer="state.max_time">
                                                        @foreach ($max_time_options as $field => $translate) 
                                                            <option value="{{$translate}}">{{__('group.max_time_options.'.$translate)}}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('max_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>  
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col">
                                                <label>@lang('group.calendar_colors')</label>
                                            </div>
                                        </div>
                                        <div class="row align-items-end">
                                            <div class="col-4">
                                                <div class="form-group">
                                                    <label>@lang('group.color_default')</label>
                                                    <div class="input-group">
                                                        <input wire:model.defer="state.color_default" data-fallbackColor="{{ $default_colors['color_default'] }}" type="text" class="form-control group-colorpicker" id="color_default" />
                                                        <div class="input-group-append">
                                                            <span class="input-group-text"><i class="fas fa-square" @if ($state['color_default']) style="color: {{ $state['color_default'] }};" @endif></i></span>
                                                        </div>
                                                    </div>                                                    
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="form-group">
                                                    <label>@lang('group.color_empty')</label>
                                                    <div class="input-group">
                                                        <input wire:model.defer="state.color_empty" data-fallbackColor="{{ $default_colors['color_empty'] }}" type="text" class="form-control group-colorpicker" id="color_empty" />
                                                        <div class="input-group-append">
                                                            <span class="input-group-text"><i class="fas fa-square" @if ($state['color_empty']) style="color: {{ $state['color_empty'] }};" @endif></i></span>
                                                        </div>
                                                    </div>                                                    
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="form-group">
                                                    <label>@lang('group.color_someone')</label>
                                                    <div class="input-group">
                                                        <input wire:model.defer="state.color_someone" data-fallbackColor="{{ $default_colors['color_someone'] }}" type="text" class="form-control group-colorpicker" id="color_someone" />
                                                        <div class="input-group-append">
                                                            <span class="input-group-text"><i class="fas fa-square" @if ($state['color_someone']) style="color: {{ $state['color_someone'] }};" @endif></i></span>
                                                        </div>
                                                    </div> 
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row align-items-end">
                                            <div class="col-4">
                                                <div class="form-group">
                                                    <label>@lang('group.color_minimum')</label>
                                                    <div class="input-group">
                                                        <input wire:model.defer="state.color_minimum" data-fallbackColor="{{ $default_colors['color_minimum'] }}" type="text" class="form-control group-colorpicker" id="color_minimum" />
                                                        <div class="input-group-append">
                                                            <span class="input-group-text"><i class="fas fa-square" @if ($state['color_minimum']) style="color: {{ $state['color_minimum'] }};" @endif></i></span>
                                                        </div>
                                                    </div> 
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="form-group">
                                                    <label>@lang('group.color_maximum')</label>
                                                    <div class="input-group">
                                                        <input wire:model.defer="state.color_maximum" data-fallbackColor="{{ $default_colors['color_maximum'] }}" type="text" class="form-control group-colorpicker" id="color_maximum" />
                                                        <div class="input-group-append">
                                                            <span class="input-group-text"><i class="fas fa-square" @if ($state['color_maximum']) style="color: {{ $state['color_maximum'] }};" @endif></i></span>
                                                        </div>
                                                    </div> 
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="form-group">
                                                    <label for="showPhone">{{__('group.showPhone')}}</label>
                                                    <small id="showPhone_help" class="form-text text-muted">
                                                        @lang('group.showPhone_help')
                                                    </small>
                                                    <select class="form-control @error('showPhone') is-invalid @enderror" id="showPhone" wire:model.defer="state.showPhone" aria-describedby="showPhone_help">
                                                        <option value="0">@lang('No')</option>
                                                        <option value="1">@lang('Yes')</option>
                                                    </select>
                                                    @error('showPhone')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>
                                            </div>
                                        </div>
                                        @if (count(Config('available_languages')) > 1)
                                        <div class="row alert alert-light">
                                            <div class="col-12">
                                                <label for="group_languages">{{__('group.group_languages')}}</label>
                                                <div class="p-1">@lang('group.group_languages_info')</div>
                                                @foreach (Config('available_languages') as $code => $value)
                                                    @if (!$value['visible'] && (auth()->user()->role !== "mainAdmin" && auth()->user()->role !== "translator"))
                                                        @continue
                                                    @endif

                                                    <div class="form-check">
                                                        <input wire:model.defer="state.languages.{{$code}}" value="1" type="checkbox" id="language_{{ $code }}" />
                                                        <label class="form-check-label" for="language_{{ $code }}">
                                                            {{ $value['name'] }}
                                                        </label>
                                                    </div>

                                                @endforeach
                                                @error('group_languages')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                
                                            </div>
                                        </div>
                                        @endif
                                    </div>                                    
                                </div>                                                      
                            </div>
                        </div>
                        {{-- end of group datas card --}}                        
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <div class="card-title">@lang('group.signs.title')</div>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                      <i class="fas fa-minus"></i>
                                    </button>
                                  </div>
                            </div>
                            <div class="card-body">
                                <div class="row"><div class="col">@lang('group.signs.info')</div></div>
                                @if (($group->copy_from_parent['signs'] ?? null) == true)
                                    <div class="alert alert-info">
                                        <i class="fas fa-info mr-1"></i> @lang('group.sub-group-alert', ['groupName' => $parent_group['name']])
                                    </div>
                                @else
                                    @foreach ($defaultSigns as $sign)
                                    <div class="input-group mb-1">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                <input wire:model="state.signs.{{$sign}}.checked" type="checkbox" />
                                            </div>
                                            <button class="btn btn-outline-success" style="width:45px;" type="button">
                                                <i class="fa {{$sign}}"></i>
                                            </button>
                                        </div>
                                        @if($state['signs'][$sign]['checked'] ?? false)
                                            <input type="text" wire:model.defer="state.signs.{{$sign}}.name" class="form-control" placeholder="@lang('group.signs.name')">
                                            <div class="input-group-append">
                                                <span class="input-group-text">@lang('group.signs.change')</span>
                                                <select wire:model.defer="state.signs.{{$sign}}.change_self" class="form-control">
                                                    <option value="0">@lang('No')</option>
                                                    <option value="1">@lang('Yes')</option>
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                    @endforeach
                                @endif                                
                            </div>
                        </div>
                        {{-- End of group signs card --}}                        
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <div class="card-title">@lang('group.literature.title')</div>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                      <i class="fas fa-minus"></i>
                                    </button>
                                  </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <input wire:model.defer="state.literatureAdd" type="text" class="form-control" placeholder="@lang('group.literature.language')" />
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-primary" wire:click="literatureAdd" wire:loading.attr="disabled">
                                            <i class="fa fa-plus mr-1"></i>
                                                @lang('Add')                                        
                                    </div>
                                </div>
                                <div class="grid-striped">
                                @foreach ($literatures as $type => $languages)
                                    @if($type == "removed") @continue; @endif
                                    @foreach ($languages as $id => $language)
                                    <div class="row p-2">
                                        @if($editedLiteratureType == $type && $editedLiteratureId == $id)
                                            <div class="col-md-6 my-auto">
                                                <input wire:model.defer="state.editedLiterature" type="text" class="form-control" value="{{ $language }}" />
                                            </div>
                                            <div class="col-md-6 text-right my-auto">
                                                <button type="button" class="btn btn-primary btn-sm mr-2" wire:click="literatureEditSave()" wire:loading.attr="disabled">
                                                    <i class="fa fa-save mr-1"></i>
                                                        @lang('Save')
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm" wire:click="literatureEditCancel()" wire:loading.attr="disabled">
                                                    <i class="fa fa-times mr-1"></i>
                                                        @lang('Cancel')
                                                </button>
                                            </div>   
                                        @else                                        
                                            <div class="col-md-6 my-auto">
                                                {{ $language }}
                                            </div>
                                            <div class="col-md-6 text-right my-auto">
                                                <button type="button" class="btn btn-primary btn-sm mr-2" wire:click="literatureEdit('{{$type}}', {{ $id }})" wire:loading.attr="disabled">
                                                    <i class="fa fa-edit mr-1"></i>
                                                        @lang('Edit')
                                                <button type="button" class="btn btn-danger btn-sm" wire:click="literatureRemove('{{$type}}', {{ $id }})" wire:loading.attr="disabled">
                                                    <i class="fa fa-trash mr-1"></i>
                                                        @lang('Remove')
                                            </div>                                        
                                        @endif
                                    </div>
                                    @endforeach
                                    
                                @endforeach
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        @lang('group.literature.help')
                                    </div>
                                </div>
                            </div>
                        </div> <!-- end of literatures section -->
                    </div> <!-- end of left section -->
                    <div class="col-lg-6">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <div class="card-title">{{__('group.days_head')}}</div>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                      <i class="fas fa-minus"></i>
                                    </button>
                                  </div>
                            </div>
                            <div class="card-body">
                                    {{-- {{ dd($days) }} --}}
                                    {{-- {{ var_dump($disabled_slots) }} --}}
                                    @foreach ($group_days as $day => $translate) 
                                        <div class="row alert alert-light p-1 mb-2">
                                            <div class="col-lg-3">
                                                <label>@lang('statistics.day')</label>
                                                <div class="form-group">                                                        
                                                    <input data-day="{{$day}}" wire:model="days.{{$day}}.day_number" type="checkbox" 
                                                        class="day-enable" id="day_{{$day}}" name="days[{{$day}}][day_number]" value="{{$day}}">
                                                    <label class="form-check-label" for="day_{{$day}}">{{__('group.days.'.$translate)}}</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                @if(isset($day_selects[$day]))
                                                    <div class="form-group">
                                                        <label for="day_{{$day}}_start_time">{{__('group.start_time')}}</label>
                                                        <select 
                                                        @if (!isset($days[$day]['day_number'])) disabled @endif
                                                        data-day="{{$day}}" wire:ignore.self wire:model="days.{{$day}}.start_time" 
                                                            name="days[{{$day}}][start_time]" id="day_{{$day}}_start_time" 
                                                            class="timeselect start_time form-control 
                                                            @if ($errors->has('days.' .$day. '.start_time')) is-invalid @endif">

                                                            @foreach ($day_selects[$day]['start'] as $time)
                                                                <option value="{{$time}}">{{ $time }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="col-lg-4">
                                                @if(isset($day_selects[$day]))
                                                    <div class="form-group">
                                                        <label for="day_{{$day}}_end_time">{{__('group.end_time')}}</label>
                                                        <select 
                                                        @if (!isset($days[$day]['day_number'])) disabled @endif
                                                        data-day="{{$day}}" wire:ignore.self wire:model="days.{{$day}}.end_time" 
                                                            name="days[{{$day}}][end_time]" id="day_{{$day}}_end_time" 
                                                            class="timeselect end_time form-control 
                                                            @if ($errors->has('days.' .$day. '.end_time')) is-invalid @endif">

                                                            @foreach ($day_selects[$day]['end'] as $time)
                                                                <option value="{{$time}}">{{ $time }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('{{$day}}.end_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                    </div> 
                                                @endif
                                            </div>
                                            @if ($errors->has('' .$day. '.start_time') || $errors->has('' .$day. '.end_time'))
                                                <div class="col-lg-12">
                                                    <small class="text-danger">
                                                    {{ $errors->first('' .$day. '.start_time') }}
                                                    {{ $errors->first('' .$day. '.end_time') }}
                                                    </small>
                                                </div>
                                            @endif
                                            @if(isset($day_selects[$day]))
                                                <div class="col-lg-12">
                                                    <label for="disabled_slots_{{$day}}">
                                                        <i class="fas fa-ban mr-1"></i>
                                                        @lang('group.disabled_time_slots'):</label>
                                                    @if  (count($disabled_slots[$day] ?? []) > 0)
                                                        <span class="ml-2 badge badge-warning">@lang('app.total'): {{ count(array_filter($disabled_slots[$day] ?? [])) }}</span>
                                                    @endif
                                                    <br/>
                                                    @lang('group.disabled_time_slots_info')
                                                    
                                                    <div class="ml-2 w-100 border  @if  (count(array_filter($disabled_slots[$day] ?? [])) > 0) border-warning @else border-secondary @endif rounded" style="height:100px;overflow-y:auto;">
                                                        @foreach ($disabled_selects[$day] ?? [] as $key => $time)
                                                        <div class="ml-2 form-check">
                                                            <input wire:model="disabled_slots.{{$day}}.{{ $time }}" class="form-check-input" type="checkbox" id="disabled_{{ $day }}_{{ $time }}">
                                                            <label class="form-check-label" for="disabled_{{ $day }}_{{ $time }}" role="button">
                                                                {{ $time }}
                                                            </label>
                                                        </div>
                                                        @endforeach
                                                    </div>                                                
                                                </div>
                                            @endif
                                        </div>                                                                          
                                    @endforeach
                                    <div class="row">
                                        <div class="col-12">@lang('group.days_info')</div>
                                    </div>
                            </div>
                        </div> <!-- end of days section -->
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <div class="card-title">@lang('group.messages.title')</div>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                      <i class="fas fa-minus"></i>
                                    </button>
                                  </div>
                            </div>
                            <div class="card-body">
                                @lang('group.messages.admin.info')<br/>
                                @lang('group.messages.info')
                                <div class="border border-secondary rounded mx-1 p-2">

                                    <div class="form-group row">
                                        <label for="messages_on" class="col-md-6 col-form-label">@lang('group.messages.admin.activate')</label>
                                        <div class="col-md-6">
                                            <select wire:model="state.messages_on" id="messages_on" class="form-control">
                                                <option value="0">@lang('No')</option>
                                                <option value="1">@lang('Yes')</option>
                                            </select>
                                        </div>
                                    </div>
                                    @if (($state['messages_on'] ?? 0) == 1)
                                        <div class="form-group row">
                                            <label for="messages_write" class="col-md-6 col-form-label">@lang('group.messages.admin.who_can_write')</label>
                                            <div class="col-md-6">
                                                <select id="messages_write" wire:model.defer="state.messages_write" class="form-control">
                                                    <option value="0">@lang('group.messages.admin.anyone')</option>
                                                    <option value="1">@lang('group.messages.admin.authorized_only')</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="messages_priority" class="col-md-6 col-form-label">@lang('group.messages.admin.priority')</label>
                                            <div class="col-md-6">
                                                <select wire:model.defer="state.messages_priority" id="messages_priority" class="form-control" aria-describedby="priority_help">
                                                    <option value="0">@lang('No')</option>
                                                    <option value="1">@lang('Yes')</option>
                                                </select>
                                                
                                            </div>
                                            <small id="priority_help" class="form-text text-muted mx-2">
                                                @lang('group.messages.admin.priority_info')
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                @if (!isset($future_changes))
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="card card-primary card-outline p-2">
                                <div class="card-body">
                                    @lang('group.update.info')
                                    <div class="form-inline">
                                        <label for="date_from">@lang('group.update.from'):</label>
                                        <input wire:model="change_date" type="date" class="form-control @error('change_date') is-invalid @enderror" id="date_from" value="" />
                                        @error('change_date')
                                            <div class="invalid-feedback" role="alert">{{$message}}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="card-footer">
                                @if ($errors->any())
                                    <p class="text-danger mt-2">
                                        {{__('app.pleaseFixErrors')}}
                                    </p>
                                @endif

                                <a href="{{route('groups')}}">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                    <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                                </a>        
                                <button type="submit" class="btn btn-primary ml-1"><i class="fa fa-save mr-1"></i>
                                    {{__('app.saveChanges')}}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>
    @section('header_style')
        <link rel="stylesheet" href="{{ asset('plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css') }}">
    @endsection
    @section('footer_scripts')
    <script src="{{ asset('plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js') }}"></script>

    <script>
        $('document').ready(function () {
            $('.day-enable').on('click', function() {            
                let day = $(this).attr('data-day');
                let enabled = $(this).is(':checked');
                $('.timeselect[data-day="'+day+'"]').attr('disabled', !enabled);
            });
            $(".group-colorpicker").colorpicker().on('change', function(event) {
                if(event.color) {
                    $(this).parent().find('.fa-square').css('color', event.color.toString());
                } else {
                    fallback = $(this).data('fallbackcolor');
                    $(this).val(fallback);
                    $(this).parent().find('.fa-square').css('color', fallback);
                }
            })
        });

        $('form').submit(function() {
            @this.set('state.color_default', $("#color_default").val());
            @this.set('state.color_empty', $("#color_empty").val());
            @this.set('state.color_someone', $("#color_someone").val());
            @this.set('state.color_minimum', $("#color_minimum").val());
            @this.set('state.color_maximum', $("#color_maximum").val());
        });

        window.addEventListener('show-literature-confirmation', event => {
            Swal.fire({
                title: '@lang('group.literature.confirmDelete.question') ' + event.detail.lang ,
                text: '@lang('group.literature.confirmDelete.message')',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '@lang('Yes')',
                cancelButtonText: '@lang('Cancel')'
            }).then((result) => {
            if (result.isConfirmed) {
                Livewire.emit('literatureDeleteConfirmed');
            }
            })
        });
        window.addEventListener('show-special_dates-confirmation', event => {
            Swal.fire({
                title: '@lang('group.special_dates.confirmDelete.question') ' + event.detail.date,
                text: '@lang('group.special_dates.confirmDelete.message')',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '@lang('Yes')',
                cancelButtonText: '@lang('Cancel')'
            }).then((result) => {
            if (result.isConfirmed) {
                Livewire.emit('dateDeleteConfirmed');
            }
            })
        });
    </script>
    @endsection
</div>