<div @if($polling) wire:poll.visible.30000ms wire:key="events-polling" @endif>
@section('title')
{{ __('app.menu-calendar') }} - {{ $cal_group_data['name'] }}
@endsection
<x-loading-indicator />
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
            <h1 class="m-0">{{ __('app.menu-calendar') }} - {{ $cal_group_data['name'] }}</h1>
            </div><!-- /.col -->
            <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item active">{{ __('app.menu-calendar') }}</li>
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
                        <div class="card-header px-1">
                            <div class="row mx-0">
                                <div class="col-12 col-md-6 ">
                                    @if (count($groups) > 1)
                                    <form wire:submit="changeGroup" class="form-inline m-0 justify-content-center justify-content-md-start">
                                        @csrf
                                        <div class="input-group">
                                            <div class="custom-file">
                                                <select wire:model="form_groupId" class="form-control" id="inlineForm">
                                                    @foreach ($groups as $group)
                                                        <option value="{{$group['id']}}" @if ($group['id'] != $cal_group_data['id']) selected @endif>{{ $group['name'] }}</option>
                                                    @endforeach
                                                </select> 
                                            </div>
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-primary" type="button">@lang('event.switch')</button>
                                            </div>
                                            </div>
                                    </form>
                                    @endif
                                </div>
                                <div class="col-12 col-lg-6 col-md-6 d-flex justify-content-center justify-content-md-end mt-4 mt-md-0">
                                    <nav>
                                        <ul class="pagination justify-content-center m-0">
                                            @if ($pagination['prev']['year'] !== false)
                                                <li class="page-item"><a class="page-link" href="{{ route('calendar') }}/{{$pagination['prev']['year']}}/{{$pagination['prev']['month']}}">@lang('Previous')</a></li>
                                            @endif
                                            <li class="page-item disabled">
                                                <a class="page-link text-nowrap" href="#" tabindex="-1" aria-disabled="true"><strong>{{$year}}. {{__("$current_month")}}</strong></a>
                                            </li>                                  
                                            <li class="page-item">
                                                <a class="page-link" href="{{ route('calendar') }}/{{$pagination['next']['year']}}/{{$pagination['next']['month']}}">@lang('Next')</a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>                                
                            </div>
                            @if ($cal_group_data['weather_enabled'] == 1 && config('weather') == 1)
                                <style>
                                    .weather-container {
                                        position: relative;
                                    }
                                    .weather-widget {
                                        display: flex;
                                        overflow: hidden;
                                        scroll-behavior: smooth;
                                    }
                                    .weather-widget .current-weather {
                                        min-width: 300px;
                                    }
                                    .weather-widget .forecast {
                                        width: 160px;
                                        text-align: center;
                                        border-right: 1px solid #ddd;
                                    }
                                    .weather-widget .forecast img, 
                                    .weather-widget .current-weather img {
                                        max-width: 50px;
                                        height: auto;
                                    }
                                    .weather-widget .font-weight-small {
                                        font-size: 0.9em;
                                    }
                                    .weather-widget .forecast:last-child {
                                        border-right: none;
                                    }
                                    .scroll-btn {
                                        position: absolute;
                                        top: 50%;
                                        transform: translateY(-50%);
                                        background-color: rgba(0, 0, 0, 0.5);
                                        color: white;
                                        border: none;
                                        padding: 10px;
                                        cursor: pointer;
                                        z-index: 10;
                                        display: none;
                                    }
                                    .scroll-btn.left {
                                        left: 0;
                                        border-radius: 0px 5px 5px 0px;
                                    }
                                    .scroll-btn.right {                                        
                                        right: 0;
                                        border-radius: 5px 0px 0px 5px;
                                    }
                                    .scroll-btn:hover {
                                        background-color: rgba(0, 0, 0, 0.7);
                                    }
                                </style>
                                <div class="row my-2">
                                    {{-- @dump($cal_group_data['weather']['forecast_weather']) --}}
                                    {{-- @dump($cal_group_data['weather']['forecasts']) --}}
                                    <div class="col-12 pt-3">

                                        <div class="weather-container mx-2 d-flex justify-content-center">
                                                <!-- Arrows for scolling -->
                                                <button class="scroll-btn left"><i class="fa fa-arrow-left"></i></button>
                                                <button class="scroll-btn right"><i class="fa fa-arrow-right"></i></button>

                                            <div class="weather-widget mx-auto rounded-lg bg-light">
                                                <!-- Current Weather -->
                                                {{-- The mere EXISTENCE of the 'current_weather' key is not
                                                     enough: for a truncated response or one carrying an
                                                     error message (e.g. 429), the 'weather' and 'main'
                                                     branches are missing from the blob, and the dereference
                                                     below threw a fatal error while rendering the calendar.
                                                     v1-patch C. --}}
                                                @if(isset(
                                                    $cal_group_data['weather']['current_weather']['weather'][0]['icon'],
                                                    $cal_group_data['weather']['current_weather']['main']['temp'],
                                                    $cal_group_data['weather']['current_weather']['name'],
                                                    $cal_group_data['weather']['current_weather']['wind']['speed']
                                                ))
                                                    <div class="current-weather p-3 border-right">
                                                        <div class="d-flex align-items-center">                                                        
                                                            <div class="ml-3">
                                                                <div class="h1 mb-0"><img src="/images/wt_icons/{{ $cal_group_data['weather']['current_weather']['weather'][0]['icon'] }}@2x.png" /> {{ number_format($cal_group_data['weather']['current_weather']['main']['temp'], 1) }}&#8451;</div>
                                                                <div class="text-muted">
                                                                    <div><b>{{ $cal_group_data['weather']['current_weather']['name'] }}</b> <span class="font-weight-small">{{ $cal_group_data['weather']['current_weather']['weather'][0]['description'] }}</span></div>
                                                                    {{-- <div class="font-weight-small">{{ $cal_group_data['weather']['current_weather']['weather'][0]['description'] }}</div> --}}
                                                                    <div class="font-weight-small">@lang('group.weather.humidity'): {{ $cal_group_data['weather']['current_weather']['main']['humidity'] }}%</div>
                                                                    <div class="font-weight-small">@lang('group.weather.wind'): {{ number_format($cal_group_data['weather']['current_weather']['wind']['speed'] * 3.6) }} @lang('group.weather.km_h') </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                                <!-- Forecasts -->
                                                <div class="d-flex">
                                                    @if(isset($cal_group_data['weather']['forecasts']))
                                                        @foreach ($cal_group_data['weather']['forecasts'] as $day => $forecast)
                                                            <div class="forecast p-3">
                                                                <div class="font-weight-bold">{{ $forecast['day'] }} - {{ __("event.weekdays_short.".$forecast['day_num']) }}</div>
                                                                {{-- <div class="text-muted font-weight-small">{{ __("event.weekdays_short.".$forecast['day_num']) }}</div> --}}
                                                                <img src="/images/wt_icons/{{ $forecast['icon'] }}@2x.png" />
                                                                <div class="font-weight-small">{{ $forecast['description'] }}</div>
                                                                <div>
                                                                    {{ number_format($forecast['max_temp']) ?? '' }}° 
                                                                    {{ number_format($forecast['min_temp']) ?? '' }}°
                                                                    <i class="fa fa-wind"></i> {{ number_format(($forecast['min_wind'] + $forecast['max_wind']) / 2 * 3.6) }} @lang('group.weather.km_h')

                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    const widget = document.querySelector('.weather-widget');
                                    const leftBtn = document.querySelector('.scroll-btn.left');
                                    const rightBtn = document.querySelector('.scroll-btn.right');

                                    // Scroll by arrows
                                    leftBtn.addEventListener('click', () => {
                                        widget.scrollBy({ left: -200, behavior: 'smooth' });
                                        updateButtons();
                                    });

                                    rightBtn.addEventListener('click', () => {
                                        widget.scrollBy({ left: 200, behavior: 'smooth' });
                                        updateButtons();
                                    });

                                    // Update after scroll
                                    widget.addEventListener('scroll', updateButtons);

                                    // Arrows visibility update
                                    function updateButtons() {
                                        const maxScrollLeft = widget.scrollWidth - widget.clientWidth;

                                        if (widget.scrollLeft <= 0) {
                                            leftBtn.style.display = 'none';
                                        } else {
                                            leftBtn.style.display = 'block';
                                        }

                                        if (widget.scrollLeft >= maxScrollLeft) {
                                            rightBtn.style.display = 'none';
                                        } else {
                                            rightBtn.style.display = 'block';
                                        }
                                    }

                                    // Touch events
                                    let startX = 0;
                                    let scrollLeft = 0;

                                    widget.addEventListener('touchstart', (e) => {
                                        startX = e.touches[0].pageX;
                                        scrollLeft = widget.scrollLeft;
                                    });

                                    widget.addEventListener('touchmove', (e) => {
                                        const x = e.touches[0].pageX;
                                        const walk = startX - x;
                                        widget.scrollLeft = scrollLeft + walk;
                                        updateButtons();
                                    });

                                    // Update arrows at startup
                                    updateButtons();
                                    //Update after window resize
                                    window.addEventListener('resize', updateButtons);

                                </script>
                                
                            @endif
                        </div>
                        <div class="card-body p-2">
                            <table class="table table-bordered eventsTable">
                                <thead>
                                    <tr>
                                        @foreach ($group_days as $dn => $translate)
                                        <th class="calendar_day">
                                            <div class="d-flex justify-content-center">
                                                <div class="d-none d-sm-block">
                                                    {{ __('group.days.'.$translate) }}
                                                </div> 
                                                <div class="d-block d-sm-none">
                                                    {{ __('event.weekdays_short.'.$translate) }}
                                                </div>
                                            </div>
                                        </th>    
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($calendar as $row)
                                <tr>
                                    @foreach ($row as $day)
                                    <td @if ($day['colspan'] !== null) colspan = "{{$day['colspan']}}" 
                                        @else
                                            class="pr-1 py-1
                                            @if ($day['current']) table-secondary
                                            @elseif (isset($cal_service_days[$day['weekDay']]) == null || !$day['available']) table-active
                                            @else table-light @endif
                                            @if ($day['service_day']) day_available @endif
                                            @if (isset($dates[$day['fullDate']]['user_event'])) userEvent @endif
                                            "
                                        @endif data-day="{{ $day['fullDate'] }}"
                                        @if ($day['service_day']) onclick="modal({{$cal_group_data['id']}}, '{{ $day['fullDate'] }}')" @endif
                                        @if (isset($dates[$day['fullDate']]['color']))
                                                    style="background: {{$dates[$day['fullDate']]['color']}}"
                                                @endif
                                        >
                                        <div class="row justify-content-end">                                            
                                            <div class="dayNumber mr-2 noselect">
                                                @if(isset($notAcceptedEvents[$day['fullDate']]))
                                                    <i class="fas fa-balance-scale-right mr-1 text-primary" title="@lang('event.status_0')"></i>
                                                @endif
                                                {{ $day['day'] }}
                                            </div>
                                        </div>
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <script>
                                function modal(groupId, date) {
                                    // livewire.emitTo('events.modal', 'openModal', date, groupId, 'events.events');
                                    // livewire.emit('pollingOff');
                                    livewire.emit('openEventsModal', date);                                    
                                }
                            </script>
                        </div>
                    </div>
                </div>
            </div>
            {{-- End of calendar --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <h5 class="card-header">
                            @lang('group.special_dates.title')
                            @if ($group_editor)
                                <button wire:click="$emitTo('groups.special-date-modal', 'openModal')" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus mr-1"></i>
                                        @lang('app.add')
                                </button>
                            @endif
                        </h5>
                        <div class="card-body pt-0">
                            <ul class="list-group list-group-flush">
                                @forelse ($specialDatesList as $date)
                                    @php                                                
                                        $carbon_date = \Carbon\Carbon::parse($date['date']);
                                        $carbon_start = \Carbon\Carbon::parse($date['date_start']);
                                        $carbon_end = \Carbon\Carbon::parse($date['date_end']);
                                    @endphp
                                    <li class="list-group-item p-2">
                                        @if ($group_editor)
                                            <button wire:click="$emitTo('groups.special-date-modal', 'openModal', '{{ $carbon_date->format("Y-m-d") }}')" class="btn btn-primary btn-sm mr-1">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        @endif
                                        <strong>{{ $carbon_date->format(__('app.format.date')) }}, {{ __('event.weekdays_short.'.( $carbon_date->format('w'))) }} 
                                            @if ($date['date_status'] == 2) 
                                                {{ $carbon_start->format(__('app.format.time')) }} - {{ $carbon_end->format(__('app.format.time')) }}
                                            @endif
                                            - {{ $date['note'] }}</strong><br/>                                        
                                        @if ($date['date_status'] == 2)
                                            {{ __('group.service_publishers', ['min' => $date['date_min_publishers'], 'max' => $date['date_max_publishers']]) }}
                                        @else 
                                            {{ __('group.special_dates.statuses_short.'.$date['date_status']) }}
                                        @endif
                                    </li>
                                @empty
                                    <li class="list-group-item p-2">@lang('group.special_dates.no_special_dates')</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
                {{-- End of special dates --}}
                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <h5 class="card-header">
                            @lang('group.color_explanation.title')
                        </h5>
                        <div class="card-body pt-0">
                            <div class="row mt-1">
                                <div class="col-12">
                                    @lang('group.color_explanation.info')
                                </div>
                            </div>                            
                            @foreach ($cal_group_data['colors'] as $field => $color)
                            <div class="row mt-2 border-bottom pb-2">
                                <div class="col-3" style="background: {{ $color }};height:25px;"></div>
                                <div class="col-9">{{ __('group.color_explanation.'. $field) }}</div>
                            </div>
                            @endforeach
                            <div class="row mt-1">
                                <div class="col-3" style="background: #ff8000;"></div>
                                <div class="col-9">{{ __('group.color_explanation.your_service') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- End of color helper --}}
            </div>
        </div>
    </div>
    @if ($group_editor)
        @livewire('groups.poster-edit-modal', '', key('poster-edit-modal-livewire'))
        @livewire('groups.special-date-modal', ['groupId' => $cal_group_data['id']], key('special-date-modal-livewire'))
    @endif    
</div>