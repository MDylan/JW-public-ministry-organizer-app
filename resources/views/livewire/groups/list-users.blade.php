<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{ __('group.users') }} ({{$group_name}})</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
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
                <div class="col-md-6 mb-2 mb-md-0">
                    <div>
                        <a href="{{ URL::previous() }}" class="btn btn-primary">
                            <i class="fas fa-arrow-alt-circle-left mr-1"></i>
                            @lang('app.back')
                        </a>
                        @if($editor) 
                            <a wire:click="$emit('openModal', 'UserAddModal')" wire:loading.attr="disabled" type="button" class="btn btn-secondary">
                                <i class="fa fa-plus mr-1"></i>
                                @lang('group.user.add.title')</a>
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
                            @if ($user->pivot->hidden == 1 && $editor == 0)
                                @continue                                        
                            @endif

                            <div class="row py-2 mx-0 mb-1">
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('user.name'):
                                    </div>
                                <div class="col-8 col-md-2 my-auto align-middle text-left text-bold">
                                    {{ $user->full_name }} 
                                    @if ($user->pivot->hidden == 1)
                                        <span class="badge badge-success"> @lang('group.hidden') </span>
                                    @endif
                                    @if($editor && $user->pivot->note) 
                                    <div class="w-100"></div><span class="badge badge-info"> {{ $user->pivot->note }} </span>
                                    @endif
                                </div>
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('user.email'):
                                    </div>
                                <div class="col-8  @if($editor) col-md-2 @else col-md-4 @endif text-left my-auto align-middle">
                                    <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                                    @if ($user->pivot->accepted_at == null)
                                        <br/><span class="badge badge-warning"> @lang('group.waiting_approval') </span>
                                    @endif 
                                </div>
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('user.phone'):
                                    </div>
                                <div class="col-8 col-md-2 text-left text-md-center my-auto align-middle">
                                    <a href="tel:+{{ $user->phone }}">{{ $user->phone }}</a>
                                </div>
                                    <div class="d-md-none col-4 text-right text-bold">
                                        @lang('user.last_login'):
                                    </div>
                                <div class="col-8 col-md-2 text-md-center text-left my-auto align-middle">
                                    @if($user->last_login_time)
                                    {{  \Carbon\Carbon::parse($user->last_login_time)->format(__('app.format.datetime')) }}
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
                                        <button wire:click="$emit('edit', {{$user->id}})" class="btn btn-sm btn-primary mr-1 mb-1">
                                            @lang('app.edit')
                                        </button>
                                        <a title="@lang('app.delete')" href="" wire:click.prevent="confirmUserRemoval({{$user->id}})" class="btn btn-sm btn-danger mr-1 mb-1">
                                           <i class="fas fa-trash"></i>
                                        </a>
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
                    </div>
                    <div class="d-flex justify-content-between p-2">
                        <div>
                        <a href="{{ URL::previous() }}" class="btn btn-primary">
                            <i class="fas fa-arrow-alt-circle-left mr-1"></i>
                            @lang('app.back')
                        </a>
                        </div>
                        <div>
                            {{$users->links()}}
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
                                {{ $selected_user['full_name'] }}
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
                                    <select wire:model="state.group_role" wire:ignore.self name="group_role" class="form-control">
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
                                    <input wire:model="state.note" wire:ignore
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
                        <div class="row mb-2">
                            <div class="col-4"></div>
                            <div class="col-8">
                                <input wire:model="state.hidden" name="hidden" wire:ignore.self type="checkbox" id="user" value="1">
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

        @isset($editor)
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
                                <textarea wire:model.defer="new_users" id="userAddField" class="form-control" name="" placeholder="{{__('group.search_placeholder')}}"
                                 cols="30" rows="4"></textarea>                              
                            </div>
                            
                          @error('email')
                            <p class="text-danger mt-2">{{$message}}</p>
                          @enderror
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
                                <i class="fa fa-plus mr-1"></i>
                                {{__('group.user_add')}}</button>
                    </x-slot>
                </x-modal>
            </form>
        @endisset

    </div>
</div>
