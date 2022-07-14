<div>
@section('title')
{{ __('app.menu-groups') }}
@endsection
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{ __('app.menu-groups') }}</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item active">{{ __('app.menu-groups') }}</li>
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
            <div class="col-lg-12">
                @if(Session::has('status'))
                    <p class="alert alert-success">{{ Session::get('status') }}</p>
                @endif
                <div class="card card-primary card-outline">
                    <div class="card-body">
                        <div class="grid-striped">
                            @forelse ($groups as $group)
                                <div class="row py-2 rounded mb-1">
                                    <div class="col-md-4 py-2 py-md-0 text-md-left text-center">
                                        @can('is-admin')
                                        {{ $group->id }}. 
                                        @endcan                                            
                                        <strong>{{ $group->name }}</strong><br/>
                                        @if($group->parent_group_id)
                                        <span class="ml-2 badge badge-secondary">
                                            @lang('group.sub-group')
                                        </span>
                                        @else
                                        <span class="ml-2 badge badge-dark">
                                            @lang('group.main-group')
                                        </span>
                                        @endif                                        
                                        <span class="ml-1 badge badge-{{ $group->group_role }}">
                                            {{ __('group.roles.'.$group->pivot->group_role) }}
                                        </span>
                                    </div>
                                    <div class="col-md-5 py-2 py-md-0 text-center my-auto">
                                        @if ($group->pivot->accepted_at !== null)
                                            <a class="btn btn-outline-secondary" href="{{ route('groups.users', ['group' => $group->id]) }}">
                                                <i class="fa fa-user-friends mr-1"></i>
                                                @lang('group.users')
                                            </a>
                                            <a class="btn btn-outline-info ml-1" href="{{ route('groups.news', ['group' => $group->id]) }}">
                                                <i class="fa fa-file mr-1"></i>
                                                @lang('group.news')</a>

                                        @else
                                            @if (!$group->parent_group_id)
                                                @lang('app.invitation')
                                                <button wire:click.prevent="accept({{$group->id}})" type="button" class="btn btn-primary">
                                                    <i class="fa fa-check mr-1"></i> @lang('app.accept')</button>
                                                <button wire:click.prevent="rejectModal({{$group->id}})" type="button" class="btn btn-danger">
                                                    <i class="fa fa-times mr-1"></i> @lang('app.reject')</button>
                                            @endif
                                        @endif
                                        </div>
                                        <div class="col-md-3 py-2 py-md-0 text-center text-md-right my-auto">
                                            @if ($group->pivot->accepted_at !== null)
                                                <div class="dropdown">
                                                <button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fa fa-hammer mr-1"></i> @lang('group.manage')
                                                </button>
                                                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                    
                                                        @if(in_array($group->pivot->group_role, ['admin', 'roler']))
                                                            <a class="dropdown-item" href="{{ route('groups.statistics', $group) }}" title="@lang('statistics.statistics')">
                                                                <i class="fa fa-chart-bar mr-1"></i> @lang('statistics.statistics')
                                                            </a>
                                                            {{-- <a class="dropdown-item" href="{{ route('groups.history', $group) }}" title="{{ __('group.history') }}">
                                                                <i class="fa fa-history mr-1"></i> @lang('group.history')
                                                            </a> --}}
                                                            <a class="dropdown-item text-info" href="{{ route('groups.edit', $group) }}" title="{{ __('app.edit') }}">
                                                                <i class="fa fa-edit mr-1"></i> @lang('app.edit')
                                                            </a>                                                            
                                                            <div class="dropdown-divider"></div>
                                                        @endif
                                                        @if (!$group->parent_group_id)
                                                            <a class="dropdown-item text-danger" href="" title="{{ __('group.logout.button') }}" wire:click.prevent="confirmLogoutModal({{$group->id}})">
                                                                <i class="fa fa-sign-out-alt mr-1" aria-hidden="true"></i>
                                                                @lang('group.logout.button')
                                                            </a>
                                                        @endif
        
                                                        @if(in_array($group->pivot->group_role, ['admin']))
                                                            <a class="dropdown-item text-danger" href="{{ route('groups.delete', $group) }}" title="{{ __('app.delete') }}">
                                                                <i class="fa fa-trash mr-1"></i>
                                                                @lang('app.delete')
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                <div class="row">
                                    <div class="col-md-12">
                                        @lang('group.notInGroup')
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        {{$groups->links()}}
                    </div>
                </div>
                @can ('is-groupcreator')
                    <div class="d-flex justify-content-end mb-2">
                        <button wire:click="openModal('createGroup')" class="btn btn-primary">
                            <i class="fa fa-plus-circle mr-1"></i>
                            {{ __('group.addNew') }}</button>
                    </div>
                @endcan
                @cannot('is-groupcreator')
                    @if (config('settings_claim_group_creator') == 1)
                        <div class="callout callout-info">
                            @lang('group.notGroupCreator', ['url' => '#'])
                            <button wire:click.prevent="askGroupCreatorPrivilege" class="btn btn-primary btn-sm">
                                {{ __('group.requestButton') }}</button>
                        </div>    
                    @endif
                @endcannot 
                </div>
            <!-- /.col-md-12 -->
            
        </div>
        <!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
    @if (config('settings_claim_group_creator') == 1)
        <!-- Modal -->
        <div class="modal fade" id="form" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true" wire:ignore.self>
            <div class="modal-dialog">
                <form autocomplete="off" wire:submit.prevent="requestGroupCreatorPrivilege">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="ModalLabel">
                                <span>
                                    {{ __('group.request.title') }}
                                </span>
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            
                                <div class="form-group">
                                    <label for="congregation">@lang('group.request.congregation')</label>
                                    <input wire:model.defer="state.congregation" name="congregation" type="text" class="form-control @error('congregation') is-invalid @enderror" id="congregation" placeholder="">
                                    @error('congregation')
                                    <div class="invalid-feedback">
                                        {{ __($message) }}.
                                    </div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                <label for="reason">@lang('group.request.reason')</label>
                                <textarea wire:model.defer="state.reason" name="reason" class="form-control @error('reason') is-invalid @enderror" id="reason" rows="3" placeholder="@lang('group.request.reason_helper')"></textarea>
                                @error('reason')
                                    <div class="invalid-feedback">
                                        {{ __($message) }}.
                                    </div>
                                    @enderror
                                </div>
                                <div class="callout callout-info">
                                    @lang('group.request.info')
                                </div>
                                @error('phone')
                                <div class="callout callout-danger">
                                    @lang('group.request.phoneError')
                                </div>
                                @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-send mr-1"></i>
                                @lang('group.request.button')
                                </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
    <!-- ConformationModal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>{{__('group.deletegroup')}}</h5>
                </div>
                <div class="modal-body">
                    <h4>{{__('group.areYouSureDelete')}}<h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                    {{-- <button type="button" wire:click.prevent="deleteGroup" class="btn btn-danger"> --}}
                        @if ($groupBeeingRemoved !== null)
                        <a href="{{ route('groups.delete', ['group' => $groupBeeingRemoved]) }}" class="btn btn-danger">
                            <i class="fa fa-trash mr-1"></i>
                            {{__('app.yesDelete')}}
                        </a>
                        @endif
                </div>
            </div>
        </div>
    </div>

    @can('is-groupcreator')
        <form autocomplete="off" wire:submit.prevent="createGroup">
            <x-modal modalId="createGroup">
                <x-slot name="title">
                    @lang('group.addNew')
                </x-slot>
            
                <x-slot name="content">
                    <div class="alert alert-info">@lang('group.create_info')</div>
                    <div class="row">
                        <div class="col-12">
                            <label for="groupName">@lang('group.name')</label>
                            <input wire:model.defer="state.name" type="text" class="form-control" name="groupName" id="groupName">
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
                            {{__('group.addNew')}}</button>
                </x-slot>
            </x-modal>
        </form>
    @endcan

    @section('footer_scripts')
    <script>
    $(document).ready(function() {
        window.addEventListener('show-logout-confirmation', event => {
            Swal.fire({
            title: '@lang('group.logout.question')',
            text: '@lang('group.logout.message')',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '@lang('Yes')',
            cancelButtonText: '@lang('Cancel')'
            }).then((result) => {
            if (result.isConfirmed) {
                // Livewire.emit('logoutConfirmed');
                window.location.replace('{{ config('app.url') }}/groups/' + event.detail.groupId + '/logout');
            }
            })
        });

        window.addEventListener('show-reject-confirmation', event => {
            Swal.fire({
            title: '@lang('group.reject_question')',
            text: '@lang('group.reject_message')',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '@lang('Yes')',
            cancelButtonText: '@lang('Cancel')'
            }).then((result) => {
            if (result.isConfirmed) {
                Livewire.emit('rejectConfirmed');
            }
            })
        });
    });
    </script>
    @endsection
</div>