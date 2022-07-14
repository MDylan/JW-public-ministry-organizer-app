<div>
@section('title')
{{ __('group.deletegroup') }}
@endsection
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">{{ __('group.deletegroup') }}</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{route('groups')}}">{{ __('app.menu-groups') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('group.deletegroup') }}</li>
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
                <div class="col">
                    <div class="card card-danger card-outline">
                        <div class="card-body">
                            <h4><i class="fas fa-exclamation-triangle mr-1"></i> @lang('group.areYouSureDelete', ['groupName' => $group->name])</h4>
                            <div class="alert alert-warning">
                                <div class="form-check">
                                    <input wire:model.defer="deleteUsers" value="1" type="checkbox" id="deleteUsers" />
                                    <label class="form-check-label" for="deleteUsers">
                                        @lang('group.deleteUsers')
                                    </label>
                                    <p id="deleteUsersHelpBlock" class="form-text text-muted">
                                        @lang('group.deleteUsersInfo')
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="{{route('groups')}}">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                            </a>        
                            <button wire:click="deleteGroup" type="submit" class="btn btn-danger ml-1"><i class="fa fa-trash mr-1"></i>
                                @lang('app.yesDelete')</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
