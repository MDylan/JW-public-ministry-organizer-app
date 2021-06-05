<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{ __('app.menu-calendar') }}</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
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
                        <div class="card-body">
                            <div class="container-fluid pl-0 pr-0">
                                <div class="row">
                                    <div class="col-md">
                                        @if (count($groups) > 1)
                                        <form wire:submit.prevent="changeGroup" class="form-inline">
                                            @csrf
                                            <div class="input-group">
                                                <div class="custom-file">
                                                    <select wire:model="form_groupId" class="form-control" id="inlineForm">
                                                        <option selected>@lang('event.choose_group')</option>
                                                        @foreach ($groups as $group)
                                                            {{-- @if ($group['id'] != $group_data['id']) --}}
                                                                <option value="{{$group['id']}}">{{ $group['name'] }}</option>
                                                            {{-- @endif --}}
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
                                    <div class="col-md d-flex justify-content-center mt-1">
                                        <h4>{{ $cal_group_data['name'] }}</h4>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                        <nav>
                                            <ul class="pagination justify-content-center">
                                                <li class="page-item"><a class="page-link" href="{{ route('calendar') }}/{{$pagination['prev']['year']}}/{{$pagination['prev']['month']}}">@lang('Previous')</a></li>
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
                            {{-- @livewire('events.calendar', ['month' => $month, 'year' => $year])  --}}
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        @foreach (trans('group.days') as $dn => $translate)
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
                                            "
                                        @endif data-day="{{ $day['fullDate'] }}"
                                        @if ($day['service_day']) onclick="modal({{$cal_group_data['id']}}, '{{ $day['fullDate'] }}')" @endif
                                        >
                                        <div class="row">
                                            <div class="col-8 m-0 p-0">
                                                @if (isset($day_stat[$day['fullDate']]))
                                                    <div class="dayStat @if (isset($userEvents[$day['fullDate']]))userEvent @endif"
                                                    style="background: {{$day_stat[$day['fullDate']]}}"></div>
                                                @endif
                                            </div>
                                            <div class="col-4 d-flex justify-content-end">{{ $day['day'] }}</div>
                                        </div>
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <script>
                                function modal(groupId, date) {
                                    livewire.emitTo('events.modal', 'openModal', date);
                                }
                            </script>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @livewire('events.modal', ['groupId' => $cal_group_data['id']], key('eventsmodal'))

</div>

