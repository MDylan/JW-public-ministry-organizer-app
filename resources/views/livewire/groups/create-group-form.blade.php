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
                <div class="col-lg-12">
                    <div class="card card-primary card-outline">
                        <form wire:submit.prevent="createGroup">
                            @csrf
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-6">
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
                                    <div class="col-lg-6">
                                        <label>{{__('group.days_head')}}</label>
                                        @foreach (trans('group.days') as $day => $translate) 
                                            <div class="row alert alert-light">
                                                <div class="col-lg-3">
                                                    <label>Nap</label>
                                                    <div class="form-group">                                                        
                                                        <input data-day="{{$day}}" wire:model.defer="state.days.{{$day}}.day_number" type="checkbox" class="day-enable" id="day_{{$day}}" name="days[{{$day}}][day_number]" value="{{$day}}">
                                                        <label class="form-check-label" for="day_{{$day}}">{{$translate}}</label>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="form-group">
                                                        <label for="day_{{$day}}_start_time">{{__('group.start_time')}}</label>
                                                        <select disabled data-day="{{$day}}" wire:ignore.self wire:model.defer="state.days.{{$day}}.start_time" 
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
                                                        <select disabled data-day="{{$day}}" wire:ignore.self wire:model.defer="state.days.{{$day}}.end_time" name="days[{{$day}}][end_time]" id="day_{{$day}}_end_time" class="timeselect start_end form-control @error('end_time') is-invalid @enderror">
                                                            @foreach (trans('group.times') as $field => $translate) 
                                                                <option value="{{$translate}}">{{$translate}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('days.{{$day}}.end_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                                    </div> 
                                                </div>
                                                @if ($errors->has('days.' .$day. '.start_time') || $errors->has('days.' .$day. '.end_time'))
                                                    <div class="col-lg-12">
                                                        <small class="text-danger">
                                                        {{ $errors->first('days.' .$day. '.start_time') }}
                                                        {{ $errors->first('days.' .$day. '.end_time') }}
                                                        </small>
                                                    </div>
                                                @endif
                                            </div>                                                                            
                                        @endforeach
                                    </div>
                                </div>
                                                      
                            </div>
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <a href="{{route('groups')}}">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                            <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                                        </a>        
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-save mr-1"></i>
                                            {{__('group.addNew')}}</button>
                                    </div>
                                    <div class="col-lg-8">
                                        @if ($errors->any())
                                            <p class="text-danger mt-2">
                                                {{__('app.pleaseFixErrors')}}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
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