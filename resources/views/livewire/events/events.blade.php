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
                            {{-- <div class="container-md mx-0 pl-0 pr-0"> --}}
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
                            {{-- </div>                             --}}
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
                                            {{-- <div class="col-8 m-0 p-0">
                                                @if (isset($day_stat[$day['fullDate']]))
                                                    <div class="dayStat @if (isset($userEvents[$day['fullDate']]))userEvent @endif"
                                                    style="background: {{$day_stat[$day['fullDate']]}}"></div>
                                                @endif
                                            </div> --}}
                                            <div class="dayNumber mr-2">{{ $day['day'] }}</div>
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

