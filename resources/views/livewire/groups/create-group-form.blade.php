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
            <div class="row">
                <div class="col-lg-6">
                    <div class="card card-primary card-outline">
                        <form wire:submit.prevent="createGroup">
                            <div class="card-body">
                                @csrf
                                <div class="form-group">
                                    <label for="inputName">{{__('group.name')}}</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="inputName" wire:model.defer="state.name" value="" placeholder="" />
                                    @error('name')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>                                
                            </div>
                            <div class="card-footer">
                                <a href="{{route('groups')}}">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                    <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                                </a>

                                <button type="submit" class="btn btn-primary"><i class="fa fa-save mr-1"></i>
                                    {{__('group.addNew')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>