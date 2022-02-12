<div wire:poll.visible.30000ms>
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
                                    <form wire:submit.prevent="changeGroup" class="form-inline m-0 justify-content-center justify-content-md-start">
                                        @csrf
                                        <div class="input-group">
                                            <div class="custom-file">
                                                <select wire:model.defer="form_groupId" class="form-control" id="inlineForm">
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
                        </div>
                        <div class="card-body p-2">
                            <table class="table table-bordered eventsTable">
                                <thead>
                                    <tr>
                                        @foreach ($group_days as $dn => $translate)
                                        <th class="calendar_day">
                                            <div class="d-flex justify-content-center">
                                                <div class="d-none d-sm-block">
                                                    {{ $translate }}
                                                </div> 
                                                <div class="d-block d-sm-none">
                                                    {{ __('event.weekdays_short.'.$dn) }}
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
                                            @if ($day['service_day']) available @endif
                                            @if (isset($userEvents[$day['fullDate']])) userEvent @endif
                                            "
                                        @endif data-day="{{ $day['fullDate'] }}"
                                        @if ($day['service_day']) onclick="modal({{$cal_group_data['id']}}, '{{ $day['fullDate'] }}')" @endif
                                        @if (isset($day_stat[$day['fullDate']]))
                                                    style="background: {{$day_stat[$day['fullDate']]}}"
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
                                    livewire.emitTo('events.modal', 'openModal', date, groupId);
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

    @livewire('events.modal', ['groupId' => $cal_group_data['id']], key('eventsmodal'))

</div>

