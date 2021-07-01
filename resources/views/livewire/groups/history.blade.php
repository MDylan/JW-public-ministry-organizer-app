<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-md-8">
            <h1 class="m-0">@lang('group.history') ({{$groupName}})</h1>
            </div><!-- /.col -->
            <div class="col-md-4">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item"> <a href="{{ route('groups') }}">{{ __('app.menu-groups') }}</a></li>
                <li class="breadcrumb-item active">@lang('statistics.statistics')</li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-md-6">
                    <a href="{{ route('groups') }}" class="btn btn-primary">
                        <i class="fas fa-arrow-alt-circle-left mr-1"></i>
                        @lang('app.back')
                    </a>
                </div>
                <div class="col-md-6 d-flex justify-content-end">
                    <div class="form-inline float-right">
                        <label class="sr-only" for="inlineFormInputGroupUsername2">@lang('statistics.month')</label>
                        <div class="input-group mb-2 mr-sm-2">
                            <div class="input-group-prepend">
                            <div class="input-group-text">@lang('statistics.month')</div>
                            </div>
                            <select wire:model.defer="state.month" class="form-control" id="inlineFormInputGroupUsername2">
                                @foreach ($months as $month => $translate)
                                    <option value="{{$month}}">{{ $translate }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button wire:loading.attr="disabled" wire:click="setMonth" type="submit" class="btn btn-primary mb-2">
                            <i class="fa fa-check-square mr-1"></i>
                            @lang('statistics.modify')
                        </button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                </div>
            </div>
        </div>
    </div>
</div>