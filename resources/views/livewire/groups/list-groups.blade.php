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
                <div class="d-flex justify-content-end mb-2">
                    <a href="{{route('groups.create')}}">
                    <button class="btn btn-primary">
                        <i class="fa fa-plus-circle mr-1"></i>
                        {{ __('group.addNew') }}</button>
                    </a>
                </div>
                
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
                                            @if(in_array($group->pivot->group_role, ['admin', 'helper']))
                                            <a href="{{ route('groups.edit', $group) }}" title="{{ __('app.edit') }}">
                                                <i class="fa fa-edit mr-2"></i>
                                            </a>
                                            @endif
                                            @if(in_array($group->pivot->group_role, ['admin']))
                                            <a href="" title="{{ __('app.delete') }}" wire:click.prevent="confirmGroupRemoval({{$group->id}})">
                                                <i class="fa fa-trash text-danger"></i>
                                            </a>
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
                                {{ __('group.editGroup') }}
                                @else
                                {{ __('group.addNew') }}
                                @endif
                            </span>
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <div class="form-group row">
                                    <label for="inputRole" class="col-sm-4 col-form-label">{{__('app.userRole')}}</label>
                                    <div class="col-sm-8">
                                        <select name="role" wire:model.defer="state.role" id="inputRole" class="form-control">
                                            @foreach (trans('roles') as $field => $translate) 
                                                <option value="{{$field}}">{{$translate}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
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
                            {{ __('app.save') }}
                            @endif</button>
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