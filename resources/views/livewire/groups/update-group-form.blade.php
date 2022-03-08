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
                                                    <select class="form-control @error('need_approval') is-invalid @enderror" id="need_approval" wire:model.defer="state.need_approval" aria-describedby="approval_help">
                                                        <option value="0">@lang('No')</option>
                                                        <option value="1">@lang('Yes')</option>
                                                    </select>
                                                    @error('need_approval')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
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
                                            <div class="col-6">
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
                                            <div class="col-6">
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
                                        </div>
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
                                                    <select name="min_time" id="min_time" class="form-control @error('min_time') is-invalid @enderror" wire:model.defer="state.min_time">
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
                                                <input wire:model.defer="state.signs.{{$sign}}.checked" type="checkbox" />
                                            </div>
                                            <button class="btn btn-outline-success" style="width:45px;" type="button">
                                                <i class="fa {{$sign}}"></i>
                                            </button>
                                        </div>
                                        <input type="text" wire:model.defer="state.signs.{{$sign}}.name" class="form-control" placeholder="@lang('group.signs.name')">
                                        <div class="input-group-append">
                                            <span class="input-group-text">@lang('group.signs.change')</span>
                                            <select wire:model.defer="state.signs.{{$sign}}.change_self" class="form-control">
                                                <option value="0">@lang('No')</option>
                                                <option value="1">@lang('Yes')</option>
                                            </select>
                                        </div>
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
                                    @foreach ($group_days as $day => $translate) 
                                        <div class="row alert alert-light p-1 mb-2">
                                            <div class="col-lg-3">
                                                <label>@lang('statistics.day')</label>
                                                <div class="form-group">                                                        
                                                    <input data-day="{{$day}}" wire:model.defer="days.{{$day}}.day_number" type="checkbox" 
                                                        class="day-enable" id="day_{{$day}}" name="days[{{$day}}][day_number]" value="{{$day}}">
                                                    <label class="form-check-label" for="day_{{$day}}">{{__('group.days.'.$translate)}}</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <label for="day_{{$day}}_start_time">{{__('group.start_time')}}</label>
                                                    <select 
                                                    @if (!isset($days[$day]['day_number'])) disabled @endif
                                                     data-day="{{$day}}" wire:ignore.self wire:model.defer="days.{{$day}}.start_time" 
                                                        name="days[{{$day}}][start_time]" id="day_{{$day}}_start_time" 
                                                        class="timeselect start_time form-control 
                                                        @if ($errors->has('days.' .$day. '.start_time')) is-invalid @endif">
                                                        @foreach ($group_times as $field => $translate) 
                                                            <option value="{{$translate}}">{{ __('group.times.'.$translate)}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <label for="day_{{$day}}_end_time">{{__('group.end_time')}}</label>
                                                    <select 
                                                    @if (!isset($days[$day]['day_number'])) disabled @endif
                                                     data-day="{{$day}}" wire:ignore.self wire:model.defer="days.{{$day}}.end_time" 
                                                        name="days[{{$day}}][end_time]" id="day_{{$day}}_end_time" 
                                                        class="timeselect end_time form-control 
                                                        @if ($errors->has('days.' .$day. '.end_time')) is-invalid @endif">
                                                        @foreach ($group_times as $field => $translate) 
                                                            <option value="{{$translate}}">{{ __('group.times.'.$translate)}}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('{{$day}}.end_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div> 
                                            </div>
                                            @if ($errors->has('' .$day. '.start_time') || $errors->has('' .$day. '.end_time'))
                                                <div class="col-lg-12">
                                                    <small class="text-danger">
                                                    {{ $errors->first('' .$day. '.start_time') }}
                                                    {{ $errors->first('' .$day. '.end_time') }}
                                                    </small>
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
                                <div class="card-title">@lang('group.special_dates.title')</div>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                      <i class="fas fa-minus"></i>
                                    </button>
                                  </div>
                            </div>
                            <div class="card-body">
                                <div class="alert @if($editedDate === null) alert-light @else alert-warning @endif">
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <label for="dateAdd_date">@lang('group.special_dates.date')</label>
                                            <input wire:model.defer="dateAdd.date" type="date" id="dateAdd_date" class="form-control @error('date') is-invalid @enderror" />
                                            @error('date')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label for="dateAdd_status">@lang('group.special_dates.date_status')</label>
                                            <select id="dateAdd_status" wire:model="dateAdd.date_status" class="form-control @error('date_status') is-invalid @enderror">
                                                <option value="2">@lang('group.special_dates.statuses.2')</option>
                                                <option value="0">@lang('group.special_dates.statuses.0')</option>
                                            </select>
                                            @error('date_status')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-12">
                                            <label for="dateAdd_note">@lang('group.special_dates.note')</label>
                                            <input wire:model.defer="dateAdd.note" type="text" id="dateAdd_note" class="form-control @error('note') is-invalid @enderror" placeholder="@lang('group.special_dates.note_placeholder')" />
                                            @error('note')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <div class="form-group">
                                                <label for="dateAdd_min_publishers">{{__('group.min_publishers')}}</label>
                                                <input @if($dateAdd['date_status'] != 2) disabled @endif wire:model.defer="dateAdd.date_min_publishers" value="" placeholder="{{__('group.min_publishers_placeholder')}}" type="number" class="form-control @error('date_min_publishers') is-invalid @enderror" id="dateAdd_min_publishers" />
                                                @error('date_min_publishers')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-group">
                                                <label for="dateAdd_max_publishers">{{__('group.max_publishers')}}</label>
                                                <input @if($dateAdd['date_status'] != 2) disabled @endif wire:model.defer="dateAdd.date_max_publishers" type="number" class="form-control @error('date_max_publishers') is-invalid @enderror" id="dateAdd_max_publishers" value="" placeholder="{{__('group.max_publishers_placeholder')}}" />
                                                @error('date_max_publishers')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                            </div>  
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <div class="form-group">
                                                <label for="dateAdd_min_time">{{__('group.min_time')}}</label>
                                                <select @if($dateAdd['date_status'] != 2) disabled @endif class="form-control @error('date_min_time') is-invalid @enderror" wire:model.defer="dateAdd.date_min_time" id="dateAdd_min_time">
                                                    @foreach ($min_time_options as $field => $translate) 
                                                        <option value="{{$translate}}">{{__('group.min_time_options.'.$translate)}}</option>
                                                    @endforeach
                                                </select>
                                                @error('date_min_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="form-group">
                                                <label for="dateAdd_max_time">{{__('group.max_time')}}</label>
                                                <select @if($dateAdd['date_status'] != 2) disabled @endif id="dateAdd_max_time" class="form-control @error('date_max_time') is-invalid @enderror" wire:model.defer="dateAdd.date_max_time">
                                                    @foreach ($max_time_options as $field => $translate) 
                                                        <option value="{{$translate}}">{{__('group.max_time_options.'.$translate)}}</option>
                                                    @endforeach
                                                </select>
                                                @error('date_max_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                            </div>  
                                        </div>
                                    </div>
                                    <div class="row align-items-end">
                                        <div class="col-md-4">
                                            <label for="dateAdd_start">@lang('group.special_dates.date_start')</label>
                                            <select @if($dateAdd['date_status'] != 2) disabled @endif  id="dateAdd_start" wire:model.defer="dateAdd.date_start" class="form-control @error('date_start') is-invalid @enderror">
                                                @foreach ($group_times as $field => $translate) 
                                                    <option value="{{$translate}}">{{ __('group.times.'.$translate)}}</option>
                                                @endforeach
                                            </select>
                                            @error('date_start')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label for="dateAdd_end">@lang('group.special_dates.date_end')</label>
                                            <select @if($dateAdd['date_status'] != 2) disabled @endif id="dateAdd_end" wire:model.defer="dateAdd.date_end" class="form-control @error('date_end') is-invalid @enderror">
                                                @foreach ($group_times as $field => $translate) 
                                                    <option value="{{$translate}}">{{ __('group.times.'.$translate)}}</option>
                                                @endforeach
                                            </select>
                                            @error('date_end')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                        </div>
                                        @if($editedDate === null) 
                                            <div class="col-md-4 align-text-bottom mt-3 mt-md-0">
                                                <button type="button" class="btn btn-primary w-100" wire:click="dateAdd" wire:loading.attr="disabled">
                                                    <i class="fa fa-plus mr-1"></i>
                                                        @lang('Add')
                                                </button>
                                            </div>
                                        @else
                                        <div class="col-md-4 mt-3 mt-md-0">
                                            <button type="button" class="btn btn-primary btn-sm mb-2 w-100" wire:click="dateSave" wire:loading.attr="disabled">
                                                <i class="fa fa-save mr-1"></i>
                                                    @lang('app.change')
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm w-100" wire:click="dateEditCancel" wire:loading.attr="disabled">
                                                <i class="fa fa-times mr-1"></i>
                                                    @lang('Cancel')
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="grid-striped">
                                @foreach ($dates as $date)
                                    @if($date['type'] == "removed") @continue; @endif
                                    {{-- @foreach ($types as $id => $date) --}}
                                    <div class="row p-2">                                   
                                        <div class="col-md-8 my-auto">
                                            <strong>{{ $date['note'] }}</strong><br/>
                                            @php                                                
                                                $carbon_date = \Carbon\Carbon::parse($date['date']);
                                            @endphp
                                           <strong>{{ $carbon_date->format(__('app.format.date')) }}, {{ __('event.weekdays_short.'.( $carbon_date->format('w'))) }}</strong>
                                            @if ($date['date_status'] == 2)
                                                {{ $date['date_start'] }} - {{ $date['date_end'] }}<br/>
                                                {{ __('group.service_publishers', ['min' => $date['date_min_publishers'], 'max' => $date['date_max_publishers']]) }}<br/>
                                                {{ __('group.service_time', ['min' => __('group.min_time_options.'.$date['date_min_time']), 'max' => __('group.max_time_options.'.$date['date_max_time'])]) }}
                                            @else 
                                                {{ __('group.special_dates.statuses_short.'.$date['date_status']) }}
                                            @endif
                                           
                                        </div>
                                        <div class="col-md-4 text-center my-auto">
                                            {{-- {{ $editedDate['type'] }} {{ $editedDate['id'] }} {{ $type }} {{ $id }} --}}
                                            @if ($editedDate == $date['date']) @lang('group.special_dates.under_edit')
                                            @else
                                                @if(!$carbon_date->isPast()) 
                                                    <button type="button" class="btn btn-primary btn-sm w-100 mb-2" wire:click="dateEdit('{{ $date['date'] }}')" wire:loading.attr="disabled">
                                                        <i class="fa fa-edit mr-1"></i>
                                                            @lang('Edit')
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm w-100" wire:click="dateRemove('{{ $date['date'] }}')" wire:loading.attr="disabled">
                                                        <i class="fa fa-trash mr-1"></i>
                                                            @lang('Remove')
                                                    </button>
                                                @endif
                                            @endif
                                        </div>                                        
                                    </div>
                                    {{-- @endforeach --}}
                                    
                                @endforeach
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        @lang('group.special_dates.info')
                                    </div>
                                </div>
                            </div>
                        </div> <!-- end of spacial dates section -->
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-4">
                        <a href="{{route('groups')}}">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                        </a>        
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save mr-1"></i>
                            {{__('app.saveChanges')}}</button>
                    </div>
                    <div class="col-lg-8">
                        @if ($errors->any())
                            <p class="text-danger mt-2">
                                {{__('app.pleaseFixErrors')}}
                            </p>
                        @endif
                    </div>
                </div>
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