<div>
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
                @can ('is-groupcreator')
                    <div class="d-flex justify-content-end mb-2">
                        <a href="{{route('groups.create')}}">
                        <button class="btn btn-primary">
                            <i class="fa fa-plus-circle mr-1"></i>
                            {{ __('group.addNew') }}</button>
                        </a>
                    </div>
                @endcan        
                <div class="card card-primary card-outline">
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">{{ __('group.name') }}</th>
                                <th scope="col">{{ __('app.role') }}</th>
                                <th scope="col">{{ __('app.options') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                                @if (count($groups) == 0) 
                                    <tr>
                                        <td colspan="5">@lang('group.notInGroup')</td>
                                    </tr>
                                @endif
                                @foreach ($groups as $group)
                                    <tr>
                                        <th scope="row">{{ $group->id }}</th>
                                        <td>{{ $group->name }}</td>
                                        <td>
                                            <span class="badge badge-{{ $group->group_role }}">
                                                {{ __('group.roles.'.$group->pivot->group_role) }}
                                            </span>
                                        </td>
                                        <td>                                            
                                            @if ($group->pivot->accepted_at == null)

                                                @lang('app.invitation')
                                                <button wire:click.prevent="accept({{$group->id}})" type="button" class="btn btn-primary btn-sm">
                                                    <i class="fa fa-check mr-1"></i> @lang('app.accept')</button>
                                                <button wire:click.prevent="rejectModal({{$group->id}})" type="button" class="btn btn-danger btn-sm">
                                                    <i class="fa fa-times mr-1"></i> @lang('app.reject')</button>
                                            @else                                               
                                            
                                                @if(in_array($group->pivot->group_role, ['admin', 'roler']))
                                                <a href="{{ route('groups.edit', $group) }}" title="{{ __('app.edit') }}" class="mr-2">
                                                    <i class="fa fa-edit"></i> @lang('app.edit')
                                                </a>
                                                @endif

                                                <a href="" title="{{ __('group.logout.button') }}" wire:click.prevent="confirmLogoutModal({{$group->id}})" class="mr-2">
                                                    <i class="fa fa-sign-out-alt text-danger" aria-hidden="true"></i>
                                                </a>

                                                @if(in_array($group->pivot->group_role, ['admin']))
                                                <a href="" title="{{ __('app.delete') }}" wire:click.prevent="confirmGroupRemoval({{$group->id}})" class="mr-2">
                                                    <i class="fa fa-trash text-danger"></i>
                                                </a>
                                                @endif

                                                

                                            @endif
                                        </td>
                                    </tr>            
                                @endforeach                
                            </tbody>                      
                        </table>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        {{$groups->links()}}
                    </div>
                </div>
                @cannot('is-groupcreator')
                    <div class="callout callout-info">
                        @lang('group.notGroupCreator', ['url' => '#'])
                        <button wire:click.prevent="askGroupCreatorPrivilege" class="btn btn-primary">
                            {{ __('group.requestButton') }}</button>
                    </div>    
                @endcannot 
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
                    <button type="button" wire:click.prevent="deleteGroup" class="btn btn-danger">
                        <i class="fa fa-trash mr-1"></i>
                        {{__('app.yesDelete')}}
                        </button>
                </div>
            </div>
        </div>
    </div>
</div>