<div>
    @foreach ($events as $event)
        @if (isset($event['groups']['name']))
            <div class="alert alert-secondary mb-2 p-1">
                <div class="row mb-2">
                    <div class="col-12 text-bold text-center">
                        {{$event['day_name']}} {{$event['full_time']}}
                    </div>
                    @if($event['status'] == 0)
                        <div class="col-12 text-center badge badge-warning p-1 mt-2 mb-2">
                            <i class="fa fa-balance-scale-right mr-1"></i>@lang('event.status_0')
                        </div>
                    @endif
                    <div class="col-12">
                        {{$event['groups']['name']}}
                    </div>
                    @if($event['status'] == 1)
                        @isset($links[$event['id']]['calendar_google'])
                            <div class="col-6 mt-2 text-center">
                                <a class="btn btn-sm btn-primary" target="_blank" href="{{ $links[$event['id']]['calendar_google'] }}">
                                    <i class="fa fa-calendar-plus mr-1"></i> @lang('event.google')
                                </a>
                            </div>
                        @endisset
                        @isset($links[$event['id']]['calendar_ics'])
                            <div class="col-6 mt-2 text-center">
                                <a class="btn btn-sm btn-primary" target="_blank" href="{{ $links[$event['id']]['calendar_ics'] }}">
                                    <i class="fa fa-calendar-alt mr-1"></i> @lang('event.ics')
                                </a>
                            </div>
                        @endisset
                    @endif
                </div>
            </div>
        @endif        
    @endforeach
</div>
