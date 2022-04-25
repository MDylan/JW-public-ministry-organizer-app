<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{ __('app.menu-users') }}</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item"><a href="#">{{ __('app.menu-admin') }}</a></li>
                <li class="breadcrumb-item active">{{ __('app.menu-users') }}</li>
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
                <div class="col-md-9 mb-2 mb-md-0">
                        <button wire:click.prevent="addNew" class="btn btn-primary">
                            <i class="fa fa-plus-circle mr-1"></i>
                            {{ __('user.addNew') }}</button>
                </div>

                <div class="col-md-3 align-self-end mb-2">            
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
        <div class="row">
            <div class="col-lg-12">
                
                <div class="card card-primary card-outline">
                    <div class="grid-striped" wire:loading.class="text-muted">
                        <div class="d-none d-md-block">
                            <div class="row py-2 mx-0">
                                <div class="col-2 text-left text-bold">@lang('user.name')</div>
                                <div class="col-3 text-left text-bold">@lang('user.email')</div>
                                <div class="col-2 text-center text-bold">@lang('app.userRole')</div>
                                <div class="col-2 text-center text-bold">@lang('user.registered')</div>
                                <div class="col-2 text-center text-bold">@lang('user.last_login')</div>
                                <div class="col-1 text-center text-bold">@lang('app.options')</div>
                            </div>
                        </div>
                        @forelse ($users as $user)

                            <div class="row py-2 mx-0 mb-1">
                                <div class="d-md-none col-4 text-right text-bold">
                                    @lang('user.name'):
                                </div>
                            <div class="col-8 col-md-2 my-auto align-middle text-left text-bold">
                                #{{ $user->id }} - {{ $user->name }} 
                            </div>
                                <div class="d-md-none col-4 text-right text-bold">
                                    @lang('user.email'):
                                </div>
                            <div class="col-8 col-md-3 text-left my-auto align-middle">
                                <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                            </div>
                                <div class="d-md-none col-4 text-right text-bold">
                                    @lang('user.role'):
                                </div>
                            <div class="col-8 col-md-2 text-left text-md-center my-auto align-middle">
                                {{ __('roles.'.$user->role) }}
                            </div>
                                <div class="d-md-none col-4 text-right text-bold">
                                    @lang('user.registered'):
                                </div>
                            <div class="col-8 col-md-2 text-left text-md-center my-auto align-middle">
                                {{ $user->created_at->format(__('app.format.datetime')) }}
                            </div>
                                <div class="d-md-none col-4 text-right text-bold">
                                    @lang('user.last_login'):
                                </div>
                            <div class="col-8 col-md-2 text-md-center text-left my-auto align-middle">
                                @if($user->last_activity)
                                {{  \Carbon\Carbon::parse($user->last_activity)->format(__('app.format.datetime')) }}
                                @else
                                -
                                @endif
                            </div>
                                <div class="d-md-none col-4 text-right text-bold">
                                    @lang('app.options'):
                                </div>
                            <div class="col-8 col-md-1 text-left my-auto align-middle">
                                <div class="d-flex justify-content-around">
                                    <a href="" title="{{ __('app.edit') }}" wire:click.prevent="edit({{$user}})">
                                        <i class="fa fa-edit mr-2"></i>
                                    </a>
                                    <a href="" title="{{ __('app.delete') }}" wire:click.prevent="confirmUserRemoval({{$user->id}})">
                                        <i class="fa fa-trash text-danger"></i>
                                    </a>
                                </div>

                            </div>
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
                    <div class="d-flex justify-content-end p-2">
                        <div>
                            {{$users->links()}}
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.col-md-12 -->            
        </div>
        <!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
    <!-- Modal -->
    <div class="modal fade" id="form" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <form autocomplete="off" wire:submit.prevent="{{ $showEditModal ? 'updateUser' : 'createUser' }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ModalLabel">
                            <span>
                                @if($showEditModal)
                                {{ __('user.editUser') }}
                                @else
                                {{ __('user.addNew') }}
                                @endif
                            </span>
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group row">
                            <label for="name" class="col-sm-4 col-form-label text-right">@lang('Full name')</label>
                            <div class="col-sm-8">
                                <input type="text" wire:model.defer="state.name" name="name" class="form-control @error('name') is-invalid @enderror" id="name" placeholder="@lang('Full name')">
                                @error('name')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label for="InputEmail" class="col-sm-4 col-form-label text-right">@lang('Email')</label>
                            <div class="col-sm-8">
                                <input type="email" wire:model.defer="state.email" name="email" class="form-control @error('email') is-invalid @enderror" id="InputEmail" placeholder="@lang('Email')">
                                @error('email')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="InputPhone" class="col-sm-4 col-form-label text-right">@lang('user.phone')</label>
                            <div class="col-sm-8">
                                <input type="number" wire:model.defer="state.phone_number" name="phone_number" class="form-control @error('phone_number') is-invalid @enderror" id="InputPhone" placeholder="@lang('user.phone')" aria-describedby="phoneHelpBlock">
                                @error('phone_number')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                <small id="phoneHelpBlock" class="form-text text-muted">
                                    {{__('app.justNumber')}}
                                </small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="form-group row">
                                    <label for="inputRole" class="col-sm-4 col-form-label">{{__('app.userRole')}}</label>
                                    <div class="col-sm-8">
                                        <select name="role" wire:model.defer="state.role" id="inputRole" class="form-control @error('role') is-invalid @enderror">
                                            @foreach ($roles as $field => $translate) 
                                                <option value="{{$translate}}">{{ __('roles.'.$translate)}}</option>
                                            @endforeach
                                        </select>
                                        @error('role')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
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
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save mr-1"></i>
                            @if($showEditModal)
                            {{__('app.saveChanges')}}
                            @else
                            @lang('user.addNew')
                            @endif</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>