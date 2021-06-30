<div>
    @foreach ($events as $event)
        @if (isset($event['groups']['name']))
            <div class="row mb-2">
                <div class="col-12 text-bold text-center">
                    {{$event['day_name']}} {{$event['full_time']}}
                </div>
                <div class="col-12">
                    {{$event['groups']['name']}}
                </div>
                <div class="col-6 text-center">
                    <a class="btn btn-sm btn-primary" target="_blank" href="{{ $event['calendar_google'] }}">
                        <i class="fa fa-calendar-plus mr-1"></i> @lang('event.google')
                    </a>
                </div>
                <div class="col-6 text-center">
                    <a class="btn btn-sm btn-primary" target="_blank" href="{{ $event['calendar_ics'] }}">
                        <i class="fa fa-calendar-alt mr-1"></i> @lang('event.ics')
                    </a>
                </div>
            </div>
        @endif        
    @endforeach
</div>
