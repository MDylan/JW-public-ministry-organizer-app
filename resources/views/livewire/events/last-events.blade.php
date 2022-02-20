<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-md-8">
            <h1 class="m-0">@lang('app.menu-lastevents')</h1>
            </div><!-- /.col -->
            <div class="col-md-4">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item active">@lang('app.menu-lastevents')</li>
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
                <div class="col-md-12 d-flex justify-content-md-end justify-content-center">
                    <div class="form-inline float-right">
                        <label class="sr-only" for="inlineFormInputGroupUsername2">@lang('statistics.month')</label>
                        <div class="input-group form-group-md mb-2 mr-sm-2">
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
                    <div class="card card-primary card-outline">
                        <div class="grid-striped">
                        <div class="d-none d-md-block">
                            <div class="row py-2 mx-0">
                                <div class="col-4 text-center text-bold">@lang('group.name')</div>
                                <div class="col-2 text-center text-bold">@lang('statistics.day')</div>
                                <div class="col-2 text-center text-bold">@lang('event.event_time')</div>
                                <div class="col-2 text-right text-bold">@lang('statistics.day_stats.service_hours')</div>
                                <div class="col-2"></div>
                            </div>
                        </div>
                        @php
                        $total = ['hours' => 0];                                        
                            @endphp
                        @forelse ($events as $event)
                        @php
                            $total['hours'] += $event->service_hour;
                        @endphp
                            <div class="row py-2 mx-0 mb-2">
                                <div class="col-md-4 my-auto align-middle text-md-left text-center text-bold">
                                    {{ $event->groups->name }}
                                </div>
                                <div class="col-6 col-md-2 text-center my-auto align-middle">
                                    {{ $event->day_name }}
                                </div>
                                <div class="col-6 col-md-2 text-center my-auto align-middle">
                                    {{ $event->full_time }}
                                </div>
                                <div class="d-md-none col-6 text-center">
                                    @lang('statistics.day_stats.service_hours'):
                                </div>
                                <div class="col-6 col-md-2 text-md-right text-center my-auto align-middle">
                                       {{ $event->service_hour }}
                                </div>
                                <div class="col-md-2 text-center my-auto align-middle">
                                    @if (count($event->groups->literatures))
                                        <button wire:click="editReports({{ $event->id }})" type="button" class="mt-3 mt-md-0 btn @if (count($event->serviceReports)) btn-success @else btn-primary @endif">
                                            <i class="fa fa-list-alt mr-1"></i>
                                            @lang('event.service.placements')
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="row py-2 mx-0">
                                <div class="col-md-12 text-center">@lang('event.no_last_events')</div>
                            </div>
                        @endforelse
                        </div>
                        <div class="row py-2 mx-0">
                            <div class="col-6 col-md-8 text-center text-md-right text-bold">@lang('statistics.summary'):</div>
                            <div class="col-6 col-md-2 text-center text-md-right text-bold">{{ $total['hours'] }}</div>
                            <div class="col-md-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


<!-- Modal -->
<div class="modal fade" id="ReportForm" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-lg reportModal">
        <form autocomplete="off" wire:submit.prevent="saveReport">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ModalLabel">
                        @lang('event.service.placements') @if($eventForm) - {{ $eventForm->groups->name }} {{ $eventForm->day_name }}, {{ $eventForm->full_time }} @endif
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-none d-md-block">
                        <div class="row justify-content-center">
                            <div class="col-md-2 text-right text-bold">@lang('event.service.language')</div>
                            <div class="col-md-2 text-center text-bold">@lang('event.service.placements')</div>
                            <div class="col-md-2 text-center text-bold">@lang('event.service.videos')</div>
                            <div class="col-md-2 text-center text-bold">@lang('event.service.return_visits')</div>
                            <div class="col-md-2 text-center text-bold">@lang('event.service.bible_studies')</div>
                            {{-- <div class="col-md-2 text-center text-bold">@lang('event.service.note')</div> --}}
                        </div>
                    </div>
                    @if($eventForm)
                        @foreach ($eventForm->groups->literatures as $literature)
                            <div class="row mb-1 justify-content-center">
                                <div class="col-md-2 my-auto text-bold text-md-right text-center">
                                    {{ $literature->name }}
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group row m-0 mb-sm-2">
                                        <label class="d-md-none col-sm-4 col-form-label" for="{{$literature->id}}_placements">@lang('event.service.placements'):</label>
                                        <div class="col-sm-8 col-md-12">
                                            <input @if($eventFormDisabled) disabled @endif wire:model.defer="reports.{{$literature->id}}.placements" type="number" class="form-control @if ($errors->has($literature->id. '.placements')) is-invalid @endif" id="{{$literature->id}}_placements" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group row m-0 mb-sm-2">
                                        <label class="d-md-none col-sm-4 col-form-label" for="{{$literature->id}}_videos">@lang('event.service.videos'):</label>
                                        <div class="col-sm-8 col-md-12">
                                            <input @if($eventFormDisabled) disabled @endif wire:model.defer="reports.{{$literature->id}}.videos" type="number" class="form-control @if ($errors->has($literature->id. '.videos')) is-invalid @endif" id="{{$literature->id}}_videos" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group row m-0 mb-sm-2">
                                        <label class="d-md-none col-sm-4 col-form-label" for="{{$literature->id}}_return_visits">@lang('event.service.return_visits'):</label>
                                        <div class="col-sm-8 col-md-12">
                                            <input @if($eventFormDisabled) disabled @endif wire:model.defer="reports.{{$literature->id}}.return_visits" type="number" class="form-control @if ($errors->has($literature->id. '.return_visits')) is-invalid @endif" id="{{$literature->id}}_return_visits" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group row m-0 mb-sm-2">
                                        <label class="d-md-none col-sm-4 col-form-label" for="{{$literature->id}}_bible_studies">@lang('event.service.bible_studies'):</label>
                                        <div class="col-sm-8 col-md-12">
                                            <input @if($eventFormDisabled) disabled @endif wire:model.defer="reports.{{$literature->id}}.bible_studies" type="number" class="form-control @if ($errors->has($literature->id. '.bible_studies')) is-invalid @endif" id="{{$literature->id}}_bible_studies" />
                                        </div>
                                    </div>
                                </div>
                                {{-- <div class="col-md-2">
                                    <div class="form-group row m-0 mb-sm-2">
                                        <label class="d-md-none col-sm-4 col-form-label" for="{{$literature->id}}_note">@lang('event.service.note'):</label>
                                        <div class="col-sm-8 col-md-12">
                                            <input wire:model.defer="reports.{{$literature->id}}.note" type="text" class="form-control @if ($errors->has($literature->id. '.note')) is-invalid @endif" id="{{$literature->id}}_note" />
                                        </div>
                                    </div>
                                </div> --}}
                            </div>
                        @endforeach
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times mr-1"></i>{{ __('app.cancel') }}</button>
                    @if(!$eventFormDisabled)
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save mr-1"></i>
                        {{ __('app.save') }}
                    </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

</div>
