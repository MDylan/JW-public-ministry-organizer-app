<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{ __('group.addNew') }}</h1>
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
            <form wire:submit.prevent="createGroup">
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
                                        <div class="row">
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="max_extend_days">{{__('group.max_extend_days')}}</label>
                                                    <input type="number" class="form-control @error('max_extend_days') is-invalid @enderror" id="max_extend_days" wire:model.defer="state.max_extend_days" value="" placeholder="{{__('group.max_extend_days_placeholder')}}" />
                                                    @error('max_extend_days')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
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
                                    </div>                                    
                                </div>                                                      
                            </div>
                        </div><!-- -->
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
                                <div class="col-lg-12">
                                    @foreach ($group_days as $day => $translate) 
                                        <div class="row alert alert-light p-1">
                                            <div class="col-lg-3">
                                                <label>Nap</label>
                                                <div class="form-group">                                                        
                                                    <input data-day="{{$day}}" wire:model.defer="state.days.{{$day}}.day_number" type="checkbox" class="day-enable" id="day_{{$day}}" name="days[{{$day}}][day_number]" value="{{$day}}">
                                                    <label class="form-check-label" for="day_{{$day}}">{{__('group.days.'.$translate)}}</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="form-group">
                                                    <label for="day_{{$day}}_start_time">{{__('group.start_time')}}</label>
                                                    <select disabled data-day="{{$day}}" wire:ignore.self wire:model.defer="state.days.{{$day}}.start_time" 
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
                                                    <select disabled data-day="{{$day}}" wire:ignore.self wire:model.defer="state.days.{{$day}}.end_time" name="days[{{$day}}][end_time]" id="day_{{$day}}_end_time" class="timeselect start_end form-control @error('end_time') is-invalid @enderror">
                                                        @foreach ($group_times as $field => $translate) 
                                                            <option value="{{$translate}}">{{ __('group.times.'.$translate)}}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('days.{{$day}}.end_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div> 
                                            </div>
                                            @if ($errors->has('days.' .$day. '.start_time') || $errors->has('days.' .$day. '.end_time'))
                                                <div class="col-lg-12">
                                                    <small class="text-danger">
                                                    {{ $errors->first('days.' .$day. '.start_time') }}
                                                    {{ $errors->first('days.' .$day. '.end_time') }}
                                                    </small>
                                                </div>
                                            @endif
                                        </div>                                                                            
                                    @endforeach
                                </div>
                            </div>
                        </div>
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
                                            @if ($editedDate == $date['date'])
                                            @else
                                                <button type="button" class="btn btn-primary btn-sm w-100 mb-2" wire:click="dateEdit('{{ $date['date'] }}')" wire:loading.attr="disabled">
                                                    <i class="fa fa-edit mr-1"></i>
                                                        @lang('Edit')
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm w-100" wire:click="dateRemove('{{ $date['date'] }}')" wire:loading.attr="disabled">
                                                    <i class="fa fa-trash mr-1"></i>
                                                        @lang('Remove')
                                                </button>
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
                                                <button type="button" class="btn btn-warning btn-sm" wire:click="literatureEditCancel()" wire:loading.attr="disabled">
                                                    <i class="fa fa-times mr-1"></i>
                                                        @lang('Cancel')
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
                        </div> <!-- end of literatures -->
                    </div>
                    <div class="col-lg-6">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <div class="card-title">{{__('group.users')}}</div>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                      <i class="fas fa-minus"></i>
                                    </button>
                                  </div>
                            </div>
                            <div class="card-body p-0">
                                    <div class="form-row align-items-center ml-3 mt-2">
                                        <div class="col-auto">
                                            <label class="mr-sm-2">{{__('user.email')}}</label>
                                        </div>
                                        <div class="col-7 my-1">
                                            <textarea wire:model.defer="search" id="userAddField" class="form-control" name="" placeholder="{{__('group.search_placeholder')}}"
                                             cols="30" rows="2"></textarea>
                                          
                                      </div>
                                        <div class="col-auto my-1">
                                            <button wire:click.prevent="userAdd()" type="submit" class="btn btn-primary">
                                                <i class="fa fa-plus mr-1"></i>
                                                {{__('group.user_add')}}</button>
                                      </div>
                                      @error('email')
                                        <p class="text-danger mt-2">{{$message}}</p>
                                      @enderror
                                    </div>
                                <table class="table">
                                    <thead>
                                        <tr>
                                        <th>{{__('user.name')}}</th>
                                        <th>{{__('app.userRole')}}</th>
                                        <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($users as $slug => $user)
                                            <tr>
                                                <td>{{$user['email']}}</td>
                                                <td>
                                                    <select wire:model="users.{{$slug}}.group_role" wire:model.defer="users.{{$slug}}.group_role" name="users['{{$slug}}']['group_role']" class="form-control">
                                                        @foreach ($group_roles as $role => $translate) 
                                                            <option value="{{$translate}}">{{ __('group.roles.'.$translate)}}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="text-right py-0 align-middle">
                                                    <div class="btn-group btn-group-sm">
                                                    <a wire:click.prevent="removeUser('{{$slug}}')" href="#" class="btn btn-danger"><i class="fas fa-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="pt-1" style="border-bottom: 1px solid #000;">
                                                    <div class="row">
                                                        <div class="col-2 my-auto">
                                                            <div class="icheck-primary d-inline">
                                                                <input wire:model.defer="users.{{$slug}}.hidden" type="checkbox" id="user_{{$slug}}" value="1">
                                                                <label for="user_{{$slug}}">
                                                                    @lang('group.hidden')
                                                                </label>
                                                              </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <label class="my-1 mr-2" for="{{$slug}}_note">{{__('group.note')}}</label>
                                                        </div>
                                                        <div class="col-8">
                                                            <input wire:ignore.self wire:model.defer="users.{{$slug}}.note" 
                                                            type="text" class="form-control" name="users['{{$slug}}']['note']" id="{{$slug}}_note">
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>  
                                        @endforeach                                        
                                    </tbody>
                                </table>
                                <div class="alert alert-into">
                                    <h6><i class="fa fa-info-circle mr-2"></i>{{__('group.role_head')}}</h6>
                                    <ul>
                                        @foreach ($group_roles as $role => $translate) 
                                            <li><strong>{{ __('group.roles.'.$translate)}}</strong>: {{__('group.role_helper.'.$translate)}}</li>
                                        @endforeach
                                    </ul>
                                    {{__('group.users_helper')}}<br/>
                                    {{__('group.hidden_helper')}}<br/>
                                    {{__('group.note_helper')}}<br/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-4">
                        <a href="{{route('groups')}}">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                        </a>        
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save mr-1"></i>
                            {{__('group.addNew')}}</button>
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
</div>

@section('footer_scripts')
<script>
    $('document').ready(function () {
        $('.day-enable').on('click', function() {            
            let day = $(this).attr('data-day');
            let enabled = $(this).is(':checked');
            $('.timeselect[data-day="'+day+'"]').attr('disabled', !enabled);
        });
    });
</script>
@endsection