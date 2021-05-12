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
                                                        @foreach (trans('group.min_time_options') as $field => $translate) 
                                                            <option value="{{$field}}">{{$translate}}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('min_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                </div>
                                            </div>
                                            <div class="col">
                                                <div class="form-group">
                                                    <label for="max_time">{{__('group.max_time')}}</label>
                                                    <select name="max_time" id="max_time" class="form-control @error('max_time') is-invalid @enderror" wire:model.defer="state.max_time">
                                                        @foreach (trans('group.max_time_options') as $field => $translate) 
                                                            <option value="{{$field}}">{{$translate}}</option>
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
                                    {{-- {{ dd($days) }} --}}
                                    @foreach (trans('group.days') as $day => $translate) 
                                        <div class="row alert alert-light p-1">
                                            <div class="col-lg-3">
                                                <label>Nap</label>
                                                <div class="form-group">                                                        
                                                    <input data-day="{{$day}}" wire:model.defer="days.{{$day}}.day_number" type="checkbox" 
                                                        class="day-enable" id="day_{{$day}}" name="days[{{$day}}][day_number]" value="{{$day}}">
                                                    <label class="form-check-label" for="day_{{$day}}">{{$translate}}</label>                                                    
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
                                                        @foreach (trans('group.times') as $field => $translate) 
                                                            <option value="{{$translate}}">{{$translate}}</option>
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
                                                        name="days[{{$day}}][end_time]" id="day_{{$day}}_start_time" 
                                                        class="timeselect end_time form-control 
                                                        @if ($errors->has('days.' .$day. '.end_time')) is-invalid @endif">
                                                        @foreach (trans('group.times') as $field => $translate) 
                                                            <option value="{{$translate}}">{{$translate}}</option>
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
                                </div>
                            </div>
                        </div>
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
                                            <textarea wire:model="search" id="userAddField" class="form-control" name="" placeholder="{{__('group.search_placeholder')}}"
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
                                        {{-- {{dd($users)}} --}}
                                        @foreach ($users as $slug => $user)
                                            <tr>
                                                <td class="pb-1 align-middle">{{$user['email']}}</td>
                                                <td class="pb-1">
                                                    <select wire:model.defer="users.{{$slug}}.group_role" name="users['{{$slug}}']['group_role']" class="form-control">
                                                        @foreach (trans('group.roles') as $role => $translate) 
                                                            <option value="{{$role}}">{{$translate}}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="text-right align-middle pb-1">
                                                    <div class="btn-group btn-group-sm">
                                                    <a wire:click.prevent="removeUser('{{$slug}}')" href="#" class="btn btn-danger"><i class="fas fa-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" class="pt-1">
                                                    <div class="form-row">
                                                        <div class="col-3 d-flex justify-content-end">
                                                        <label class="my-1 mr-2" for="{{$slug}}_note">{{__('group.note')}}</label>
                                                    </div>
                                                    <div class="col-9">
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
                                        @foreach (trans('group.roles') as $role => $translate) 
                                            <li><strong>{{$translate}}</strong>: {{__('group.role_helper.'.$role)}}</li>
                                        @endforeach                                        
                                    </ul>
                                    {{__('group.users_helper')}}
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