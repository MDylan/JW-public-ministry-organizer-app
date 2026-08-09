<div>
@section('title')
{{ __('group.users') }} ({{$group_name}})
@endsection
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
            <h1 class="m-0">{{ __('group.users') }} ({{$group_name}})</h1>
            </div><!-- /.col -->
            <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{route('groups')}}">{{ __('app.menu-groups') }}</a></li>
                <li class="breadcrumb-item active">{{ __('group.users') }}</li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row mb-2 justify-content-between">
                <div class="col-md-9 mb-2 mb-md-0">
                    <div>
                        <a wire:ignore href="{{ URL::previous() }}" class="btn btn-primary mb-2 mb-md-0">
                            <i class="fas fa-arrow-alt-circle-left mr-1"></i>
                            @lang('app.back')
                        </a>
                        @if($editor && !isset($current_parent_group_id)) 
                            <a wire:click="$emitSelf('openModal', 'UserAddModal')" wire:loading.attr="disabled" type="button" class="btn btn-secondary mb-2 mb-md-0 ml-1">
                                <i class="fa fa-plus mr-1"></i>
                                @lang('group.user.add.title')</a>
                        @endif
                        @if($admin) 
                            @if (count($child_groups) > 0)
                                {{-- this is a parent group --}}
                                <a wire:click="$emitSelf('openModal', 'ChildGroupsModal')" wire:loading.attr="disabled" type="button" class="btn btn-info mb-2 mb-md-0 ml-1">
                                    <i class="fa fa-unlink mr-1"></i>
                                    @lang('group.link.parent.detach.button')</a>
                            @else

                                @if ($current_parent_group_id > 0)
                                    {{-- this is a child group --}}
                                    <a wire:click="$emitSelf('openModal', 'ParentGroupModal')" wire:loading.attr="disabled" type="button" class="btn btn-info mb-2 mb-md-0 ml-1">
                                        <i class="fa fa-unlink mr-1"></i>
                                        @lang('group.link.child.detach.button')</a>
                                @else
                                    <a wire:click="$emitSelf('openModal', 'LinkToModal')" wire:loading.attr="disabled" type="button" class="btn btn-info mb-2 mb-md-0 ml-1">
                                        <i class="fa fa-link mr-1"></i>
                                        @lang('group.link.button')</a>
                                @endif
                            
                            @endif
                        @endif
                    </div>
                </div>
                <div class="col-md-3 align-self-end">            
                    <div class="d-flex justify-content-end align-items-center border border rounded bg-white pr-2">
                        <input wire:model="searchTerm" type="text" placeholder="@lang('Search')" class="form-control border-0" />
                        
                        <div wire:loading.delay wire:target="searchTerm">
                            <div class="la-ball-clip-rotate la-dark la-sm">
                                <div></div>
                            </div>
                        </div>
                        @if (Str::length($searchTerm) > 0)
                            <div>
                                <a href="javascript:void(0);" wire:click="clearSearch">
                                    <i class="fa fa-times-circle p-2"></i>
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @if(count($child_groups) > 0 && $editor) 
                <div class="alert alert-info text-center">@lang('group.link.parent.help')</div>
            @endif
            @if($current_parent_group_id > 0 && $editor) 
                <div class="alert alert-info text-center">@lang('group.link.child.help', ['groupName' => $parent_group_name])</div>
            @endif
            <div class="card card-primary card-outline">
                <div class="row p-2">
                    <div class="col-12">
                        <div class="form-inline">
                            <div class="input-group mb-2 mr-sm-2">
                                <label>@lang('group.filter.title')</label>: 
                            </div>
                            <div class="form-check mb-2 mr-sm-2">
                                <button wire:click="filterMyself"
                                                class="btn btn-sm p-1 mr-1 
                                                @if (($filter['myself'] ?? null) == true)
                                                    btn-success
                                                @else btn-outline-secondary
                                                @endif">
                                                <i class="fas fa-portrait mx-1"></i> @lang('group.filter.myself')
                                            </button>
                                @if($group_signs) 
                                    @foreach ($group_signs as $icon => $sign)
                                        @if ($sign['checked'] ?? false)
                                            <button wire:click="filterIcon('{{ $icon }}')"
                                                class="btn btn-sm p-1 mr-1 
                                                @if (($filter['signs'][$icon] ?? null) == true)
                                                    btn-success
                                                @else btn-outline-secondary
                                                @endif" 
                                                @if(isset($sign['name'])) title="{{ $sign['name'] }}@endif ">
                                                <i class="fa {{$icon}} p-1"></i>
                                                <div class="d-md-none">@if(isset($sign['name'])){{ $sign['name'] }}@endif</div>
                                            </button>
                                        @endif
                                    @endforeach
                                @endif
                                <button wire:click="filterOnline" class="btn btn-sm p-1 mr-1 @if (($filter['online'] ?? null) == true)
                                        btn-success
                                    @else btn-outline-secondary
                                    @endif">
                                    <i class="fas fa-street-view mx-1"></i>
                                    @lang('user.online')
                                </button>
                                @if ($editor)
                                <button wire:click="filterInactive" class="btn btn-sm p-1 mr-1 @if (($filter['inactive'] ?? null) == true)
                                        btn-success
                                    @else btn-outline-secondary
                                    @endif">
                                    <i class="fas fa-user-clock mx-1"></i>
                                    @lang('user.inactive')
                                </button>
                                @endif
                                <button wire:click="filterOff" class="btn btn-sm p-1 mr-1 btn-info">
                                    <i class="fas fa-times mx-1"></i> 
                                    @lang('group.filter.off_all')
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card card-primary card-outline">
                    <div class="grid-striped" wire:loading.class="text-muted">
                        <div class="d-none d-md-block">
                            <div class="row py-2 mx-0">
                                <div class="col-2 text-left text-bold">@lang('user.name')</div>
                                <div class="@if($editor) col-2 @else col-4 @endif text-left text-bold">@lang('user.email')</div>
                                <div class="col-2 text-center text-bold">@lang('user.phone')</div>
                                <div class="col-2 text-center text-bold">@lang('user.last_login')</div>
                                <div class="col-2 text-center text-bold">@lang('app.role')</div>                                
                            </div>
                        </div>
                        @forelse ($users as $user)
                            @if ($user->pivot->accepted_at == null && $editor == 0)
                                @continue                                        
                            @endif
                            @if (($user->pivot->hidden == 1 && $user->id !== Auth::id()) && $editor == 0)
                                @continue                                        
                            @endif

                            <div class="row py-2 mx-0 mb-1 @if(auth()->user()->id == $user->id) userEvent @endif">
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('user.name'):
                                    </div>
                                <div class="col-8 col-md-2 my-auto align-middle text-left text-bold">
                                    @can('is-admin')
                                        #{{ $user->id }} 
                                    @endcan
                                    {{ $user->name }} 
                                    <div class="w-100"></div>
                                    @if($group_signs) 
                                        @foreach ($group_signs as $icon => $sign)
                                            @if ($sign['checked'] ?? false)
                                                @if($editor && !$copy_fields['signs']
                                                  || ($user->id == Auth::id() && ($sign['change_self'] ?? null) == true && !$copy_fields['signs'])
                                                 )
                                                    <button wire:click.prevent="toogleSign({{$user->id}}, '{{ $icon }}')" 
                                                        class="btn btn-sm 
                                                        @if(isset($user->pivot->signs[$icon])) 
                                                            @if ($user->pivot->signs[$icon])
                                                                btn-success 
                                                            @else
                                                                btn-outline-dark 
                                                            @endif                                                        
                                                        @else btn-outline-dark 
                                                        @endif 
                                                        p-1" 
                                                        @if(isset($sign['name'])) title="{{ $sign['name'] }}@endif ">
                                                        <i class="fa {{$icon}} p-1"></i>
                                                        <div class="d-md-none">@if(isset($sign['name'])){{ $sign['name'] }}@endif</div>
                                                    </button>
                                                @else
                                                    @if(isset($user->pivot->signs[$icon])) 
                                                        @if ($user->pivot->signs[$icon])
                                                            <i class="fa {{$icon}} p-2 border rounded bg-success"></i>
                                                        @endif
                                                    @endif
                                                @endif
                                            @endif
                                        @endforeach
                                    @endif
                                    @if ($user->pivot->hidden == 1)
                                        <span class="badge badge-success"> @lang('group.hidden') </span>
                                    @endif
                                    @if($editor && $user->pivot->note) 
                                        <span class="badge badge-info"> {{ $user->pivot->note }} </span>
                                    @endif
                                    @if((($user->show_fields['congregation'] ?? false) == true || $editor) && $user->congregation) 
                                        <span class="badge badge-primary"> 
                                            <i class="fas fa-house-user mr-1"></i> 
                                            @if(($user->show_fields['congregation'] ?? false) == false)
                                                <i class="fas fa-eye-slash mr-1 text-danger"></i>
                                            @endif
                                            {{ $user->congregation }} 
                                        </span>
                                    @endif
                                </div>
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('user.email'):
                                    </div>
                                <div class="col-8  @if($editor) col-md-2 @else col-md-4 @endif text-left my-auto align-middle">
                                    @if(($user->show_fields['email'] ?? false) == true || $editor) 
                                        <a href="mailto:{{ $user->email }}">
                                            @if(($user->show_fields['email'] ?? false) == false)
                                                <i class="fas fa-eye-slash mr-1 text-danger"></i>
                                            @endif
                                        {{ $user->email }}</a>
                                    @endif
                                    @if ($user->pivot->accepted_at == null)
                                        <br/><span class="badge badge-warning"> @lang('group.waiting_approval') </span>
                                    @endif 
                                </div>
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('user.phone'):
                                    </div>
                                <div class="col-8 col-md-2 text-left text-md-center my-auto align-middle">
                                    @if(($user->show_fields['phone'] ?? false) == true || $editor) 
                                        <a href="tel:+{{ $user->phone_number }}">
                                            @if(($user->show_fields['phone'] ?? false) == false)
                                                <i class="fas fa-eye-slash mr-1 text-danger"></i>
                                            @endif
                                        {{ $user->phone_number }}</a>
                                    @endif
                                </div>
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('user.last_login'):
                                    </div>
                                <div class="col-8 col-md-2 text-md-center text-left my-auto align-middle">
                                    @if($user->last_activity)
                                    {{  \Carbon\Carbon::parse($user->last_activity)->format(__('app.format.datetime')) }}

                                        
                                        @if ($editor)
                                            @if (\Carbon\Carbon::parse($user->last_activity)->lt(now()->subMonths(config('gdpr.settings.ttl') ?? 6)->addDays(14)))
                                                <button  wire:click="$emitSelf('openUserRenewalModal', {{$user->id}})" class="btn btn-sm btn-warning mx-1 mb-1">
                                                    <i class="fas fa-user-clock mr-1"></i>
                                                    @lang('user.inactive')!
                                                </button>
                                            @endif
                                            
                                        @endif                                        

                                    @else
                                    -
                                    @endif
                                </div>
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('app.role'):
                                    </div>
                                <div class="col-8 col-md-2 text-md-center text-left my-auto align-middle">
                                    {{ __('group.roles.'.$user->pivot->group_role) }}  
                                </div>
                                @if($editor) 
                                    <div class="col-12 col-md-2 text-center my-auto align-middle">
                                        
                                        <button wire:click="$emitSelf('editUser', {{$user->id}})" class="btn btn-sm btn-primary mr-1 mb-1">
                                            <i class="fa fa-user-edit mr-1"></i>
                                            @lang('app.edit')
                                        </button>                                        
                                        @if(!$current_parent_group_id)
                                        <a title="@lang('app.delete')" href="" wire:click.prevent="confirmUserRemoval({{$user->id}})" class="btn btn-sm btn-danger mr-1 mb-1">
                                           <i class="fas fa-trash"></i>
                                        </a>
                                        @endif
                                    </div>
                                @endif
                            </div> 
                        @empty
                        <div class="row p-3">
                            <div class="col-12 text-center text-bold">
                                <i class="fa fa-exclamation-circle mr-1"></i>
                                @lang('No Results Found.')
                            </div>
                        </div>
                        @endforelse
                        <div class="row mx-2">
                            <div class="col-md-12 align-items-center">
                                <b>@lang('app.total'): {{ $users->total() }}</b>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-md-row flex-column justify-content-between align-items-center">
                        <div class="my-2 ml-2">
                        <a wire:ignore href="{{ URL::previous() }}" class="btn btn-primary">
                            <i class="fas fa-arrow-alt-circle-left mr-1"></i>
                            @lang('app.back')
                        </a>
                        </div>
                        <div class="mr-2 pt-3">
                            {{$users->onEachSide(1)->links()}}
                        </div>
                    </div>
            </div>
        </div>
    
        @isset($state)
            <form autocomplete="off" wire:submit.prevent="updateUser">
                <x-modal modalId="UserModal">
                    <x-slot name="title">
                        @lang('app.edit')
                    </x-slot>
                
                    <x-slot name="content">
                        <div class="row mb-2">
                            <div class="col-4 text-bold text-right">
                                @lang('Full name'): 
                            </div>
                            <div class="col-8">
                                <input wire:model.defer="state.user.name" wire:ignore
                                    type="text" class="form-control" name="name" id="" aria-describedby="name_help">
                                <small id="name_help" class="form-text text-muted">
                                   @lang('app.user_will_be_notified')
                                </small>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-bold text-right">
                                @lang('user.congregation'): 
                            </div>
                            <div class="col-8">
                                <input wire:model.defer="state.user.congregation" wire:ignore
                                    type="text" class="form-control" name="congregation" id="" aria-describedby="congregation_help">
                                <small id="congregation_help" class="form-text text-muted">
                                   @lang('app.user_will_be_notified')
                                </small>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-bold text-right">
                                @lang('user.phone'): 
                            </div>
                            <div class="col-8">
                                <input wire:model.defer="state.user.phone_number" wire:ignore
                                    type="number" class="form-control" name="phone_number" id="" aria-describedby="phone_help">
                                <small id="phone_help" class="form-text text-muted">
                                   @lang('app.user_will_be_notified')
                                   @lang('user.phone_helper') <strong>362012345689</strong>
                                </small>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-bold text-right">@lang('E-Mail Address'):</div>
                            <div class="col-8">{{ $selected_user['email'] }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-bold text-right">@lang('app.role'):</div>
                            <div class="col-8">
                                <div class="input-group">
                                    <select wire:model.defer="state.group_role" wire:ignore.self name="group_role" class="form-control">
                                        @foreach ($group_roles as $role => $translate) 
                                            <option value="{{$translate}}">{{ __('group.roles.'.$translate)}}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="button-addon2" data-toggle="collapse" href="#collapseRoles" role="button" aria-expanded="false" aria-controls="collapseRoles">
                                            <i class="fa fa-question"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="collapse col-12" id="collapseRoles">
                                <div class="card card-body mt-2">
                                    <ul>
                                        @foreach ($group_roles as $role => $translate) 
                                            <li><strong>{{ __('group.roles.'.$translate)}}</strong>: {{__('group.role_helper.'.$translate)}}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-4 text-bold text-right">@lang('group.note'):</div>
                            <div class="col-8">
                                <div class="input-group">
                                    <input wire:model.defer="state.note" wire:ignore
                                    type="text" class="form-control" name="note" id="">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="button-addon2" data-toggle="collapse" href="#collapseNote" role="button" aria-expanded="false" aria-controls="collapseNote">
                                            <i class="fa fa-question"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="collapse col-12" id="collapseNote">
                                <div class="card card-body mt-2">
                                    {{__('group.note_helper')}}
                                </div>
                            </div>  
                        </div>
                        @if($state['user']['email_verified_at'] === null && $current_parent_group_id === null)
                            <div class="row mb-2">
                                <div class="col-4 text-bold text-right"></div>
                                <div class="col-8">
                                    <input wire:model.defer="state.finish_guest_registration" name="finish_guest_registration" wire:ignore.self type="checkbox" id="finish_guest_registration" value="1">
                                    <label for="finish_guest_registration">
                                        @lang('group.finish_guest_registration.label')
                                        <button class="btn btn-outline-secondary" type="button" id="button-addon2" data-toggle="collapse" href="#collapseGuestHelp" role="button" aria-expanded="false" aria-controls="collapseGuestHelp">
                                            <i class="fa fa-question"></i>
                                        </button>
                                    </label>   
                                </div>
                                <div class="collapse col-12" id="collapseGuestHelp">
                                    <div class="card card-body mt-2">
                                        {{__('group.finish_guest_registration.help')}}
                                        <b>{{__('group.finish_guest_registration.alert')}}</b>
                                    </div>
                                </div>  
                            </div>
                        @endif
                        <div class="row mb-2">
                            <div class="col-4"></div>
                            <div class="col-8">
                                <input wire:model.defer="state.hidden" name="hidden" wire:ignore.self type="checkbox" id="user" value="1">
                                    <label for="user">
                                        @lang('group.hidden') 
                                        <button class="btn btn-outline-secondary" type="button" id="button-addon2" data-toggle="collapse" href="#collapseHidden" role="button" aria-expanded="false" aria-controls="collapseHidden">
                                            <i class="fa fa-question"></i>
                                        </button>
                                    </label>                                
                            </div>
                            <div class="collapse col-12" id="collapseHidden">
                                <div class="card card-body mt-2">
                                    {{__('group.hidden_helper')}}
                                </div>
                            </div> 
                        </div>
                        @if(($messages['on'] ?? false) && !in_array($state['group_role'], ['admin', 'roler']))
                            <div class="rounded border border-secondary p-2">
                                <div class="row">
                                    <div class="form-group col-12">
                                        <label for="message_use">@lang('group.messages.user.when_use')</label>
                                        <select wire:model.defer="state.message_use" id="message_use" class="form-control">
                                            <option value="0">@lang('group.messages.user.default')</option>
                                            <option value="1">@lang('group.messages.user.cant_write')</option>
                                            <option value="2">@lang('group.messages.user.can_write')</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <input wire:model.defer="state.message_send_priority" name="hidden" wire:ignore.self type="checkbox" id="message_send_priority" value="1">
                                        <label for="message_send_priority">
                                            @lang('group.messages.user.catch_urgent') 
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                    </x-slot>
                
                    <x-slot name="buttons">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save mr-1"></i>
                            @lang('app.saveChanges')
                        </button>
                    </x-slot>
                </x-modal>
            </form>
        @endisset

        {{-- New user modal --}}

        @if($editor)
            <form autocomplete="off" wire:submit.prevent="createUser">
                <x-modal modalId="UserAddModal">
                    <x-slot name="title">
                        @lang('group.user.add.title')
                    </x-slot>
                
                    <x-slot name="content">
                        <div class="row">
                            <div class="col-12">
                                <label class="mr-sm-2">{{__('user.email')}}
                                <button class="btn btn-outline-secondary" type="button" id="button-addon2" data-toggle="collapse" href="#collapseAdd" role="button" aria-expanded="false" aria-controls="collapseAdd">
                                    <i class="fa fa-question"></i>
                                </button>
                                </label>
                                <div class="collapse col-12" id="collapseAdd">
                                    <div class="card card-body mt-2">
                                        {{__('group.user.add.info')}}
                                    </div>
                                </div> 
                                <div class="form-group">
                                    <textarea wire:model.defer="new_users" id="userAddField" class="form-control" name="" placeholder="{{__('group.search_placeholder')}}"
                                    cols="30" rows="4"></textarea>
                                    @error('email.*')
                                        <p class="text-danger mt-2">{{$message}}</p>
                                    @enderror
                                </div>
                                @if (count(Config('available_languages')) > 1)
                                    <div class="form-group">
                                        <label for="email_language">@lang('group.user.add.email_language')</label>
                                        <select wire:model.defer="email_language" class="form-control" id="email_language">
                                            @foreach (Config('available_languages') as $code => $value)
                                            @if (!$value['visible'] && (auth()->user()->role !== "mainAdmin" && auth()->user()->role !== "translator"))
                                              @continue
                                            @endif
                                            <option value="{{ $code }}">{{ $value['name'] }}</option>
                                          @endforeach
                                        </select>
                                        @error('email_language')
                                            <p class="text-danger mt-2">{{$message}}</p>
                                        @enderror
                                    </div>
                                @endif
                            </div>
                        </div>
                    </x-slot>
                
                    <x-slot name="buttons">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                        <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                                <i class="fa fa-plus mr-1"></i>
                                {{__('group.user_add')}}</button>
                    </x-slot>
                </x-modal>
            </form>

            {{-- Inactive user profile renewal modal --}}

            <form autocomplete="off" wire:submit.prevent="userRenewal">
                <x-modal modalId="userRenewalModal">
                    <x-slot name="title">
                        <i class="fas fa-user-clock mr-1"></i> 
                        @lang('user.inactiveModal.title')
                    </x-slot>
                
                    <x-slot name="content">
                        <div class="row">
                            <div class="col-12">
                                @lang('user.inactiveModal.info', [
                                        'userName' => $selected_user['user']['name'] ?? '', 
                                        'lastActivity' => \Carbon\Carbon::parse($selected_user['user']['last_activity'] ?? null)->format(__('app.format.datetime'))
                                ])
                                <div class="alert alert-warning">
                                    @lang('user.inactiveModal.deletion_time', [
                                        'lastDate' => \Carbon\Carbon::parse($selected_user['user']['last_date'] ?? null)->format(__('app.format.date'))
                                    ])<br/>
                                    @lang('user.inactiveModal.deletion_info', [
                                        'gdprMonths' => config('gdpr.settings.ttl') ?? 6
                                    ])
                                </div>
                            </div>
                        </div>
                    </x-slot>
                
                    <x-slot name="buttons">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                        <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                            <i class="fas fa-hand-holding-medical mr-1"></i>
                                @lang('user.inactiveModal.button')</button>
                    </x-slot>
                </x-modal>
            </form>

        @endif

        @if ($admin)
            <form autocomplete="off" wire:submit.prevent="linkToGroup">
                <x-modal modalId="LinkToModal">
                    <x-slot name="title">
                        @lang('group.link.button')
                    </x-slot>
                
                    <x-slot name="content">
                        <div class="row">
                            <div class="col-12">
                                <label class="mr-sm-2">@lang('group.link.child.parent_group_name'):</label>
                                <select wire:model="new_parent_group_id" name="new_parent_group_id" id="" class="form-control">
                                    <option value="0">@lang('Choose')</option>
                                    @foreach ($user_admin_groups as $group)
                                        @if ($group->id == $groupId)
                                            @continue
                                        @endif
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="alert alert-info mt-2">
                                @lang('group.link.help')<br/>
                                @lang('group.link.danger')
                            </div>
                        </div>

                        <div class="alert alert-light">@lang('group.link.child.copy_data')
                            @foreach ($copy_datas as $field => $translate)
                                <div class="ml-2 form-check">
                                    <input wire:model.defer="copy_fields.{{$field}}" class="form-check-input" type="checkbox" value="1" id="calendar_{{ $field }}">
                                    <label class="form-check-label" for="calendar_{{ $field }}" role="button">
                                        {{ $translate }}
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                    </x-slot>
                
                    <x-slot name="buttons">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                        <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                                <i class="fa fa-link mr-1"></i>
                                {{__('group.link.button')}}</button>
                    </x-slot>
                </x-modal>
            </form>
            {{-- End of linkToModal --}}

            <x-modal modalId="ChildGroupsModal">
                <x-slot name="title">
                    @lang('group.link.parent.detach.button')
                </x-slot>
            
                <x-slot name="content">
                    <div class="alert alert-info">@lang('group.link.parent.info')</div>
                    <div class="row text-bold">
                        <div class="col-8">@lang('group.link.parent.child_group_name')</div>
                        <div class="col-4 text-center">@lang('group.link.child.detach.button')</div>
                    </div>
                    @foreach ($child_groups as $child_group)
                        <div class="row mb-2">
                            <div class="col-8">{{ $child_group['name'] }}</div>
                            <div class="col-4 text-center">
                                <a wire:click.prevent="confirmChildDetach({{ $child_group['id'] }})" href="" class="btn btn-danger btn-sm">
                                    <i class="fa fa-unlink mr-1"></i>
                                    @lang('group.link.child.detach.button')
                                </a>
                            </div>
                        </div>
                    @endforeach
                </x-slot>
            
                <x-slot name="buttons">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                </x-slot>
            </x-modal>
            {{-- End of ChildGroupsModal --}}
            <form autocomplete="off" wire:submit.prevent="setCopyInfo">
                <x-modal modalId="ParentGroupModal">
                    <x-slot name="title">
                        @lang('group.link.child.detach.button')
                    </x-slot>
                
                    <x-slot name="content">
                        <div class="alert alert-info">@lang('group.link.child.info')</div>
                        <div class="row text-bold">
                            <div class="col-8">@lang('group.link.child.parent_group_name')</div>
                            <div class="col-4 text-center">@lang('group.link.child.detach.button')</div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-8">{{ $parent_group_name }}</div>
                            <div class="col-4 text-center">
                                <a wire:click.prevent="confirmParentDetach({{ $current_parent_group_id }})" href="" class="btn btn-danger btn-sm">
                                    <i class="fa fa-unlink mr-1"></i>
                                    @lang('group.link.child.detach.button')
                                </a>
                            </div>
                        </div>

                        <div class="alert alert-light">@lang('group.link.child.copy_data')
                            @foreach ($copy_datas as $field => $translate)
                                <div class="ml-2 form-check">
                                    <input wire:model.defer="copy_fields.{{$field}}" class="form-check-input" type="checkbox" value="1" id="calendar_{{ $field }}">
                                    <label class="form-check-label" for="calendar_{{ $field }}" role="button">
                                        {{ $translate }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </x-slot>
                
                    <x-slot name="buttons">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save mr-1"></i>
                            @lang('app.saveChanges')
                        </button>
                    </x-slot>
                </x-modal>
            </form>
            {{-- End of ParentGroupModal --}}

        @endif

    </div>
</div>
