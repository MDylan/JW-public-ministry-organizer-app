<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-md-8">
            <h1 class="m-0">@lang('statistics.statistics') ({{$groupName}})</h1>
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
                    <div class="card card-primary card-outline card-outline-tabs">
                        <div class="card-header p-0 border-bottom-0">
                            <!-- Tabs on top of the page -->
                            <ul wire:ignore class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
                              <li class="nav-item">
                                <a class="nav-link active" id="tabs-daily_data-tab" data-toggle="pill" href="#tabs-daily_data" role="tab" aria-controls="tabs-daily_data" aria-selected="true">
                                    <i class="fa fa-calendar-check mr-1"></i>
                                    @lang('statistics.daily_data')
                                </a>
                              </li>
                              <li class="nav-item">
                                <a class="nav-link" id="tabs-publishers_data-tab" data-toggle="pill" href="#tabs-publishers_data" role="tab" aria-controls="tabs-publishers_data" aria-selected="false">
                                    <i class="fa fa-user-friends mr-1"></i>
                                    @lang('statistics.publishers_data')
                                </a>
                              </li>
                              <li class="nav-item">
                                <a class="nav-link" id="tabs-placements_data-tab" data-toggle="pill" href="#tabs-placements_data" role="tab" aria-controls="tabs-placements_data" aria-selected="false">
                                    <i class="fa fa-list-alt mr-1"></i>
                                    @lang('event.service.placements')
                                </a>
                              </li>
                            </ul>
                            <!-- End Tabs -->
                        </div>
                        <div class="card-body">
                            <div wire:loading.delay class="w-100 text-center py-4">
                                @lang('statistics.loading')
                            </div>
                            <div class="tab-content" id="tabs-tabContent">
                                <div wire:ignore.self class="tab-pane fade active show" id="tabs-daily_data" role="tabpanel" aria-labelledby="tabs-daily_data-tab">
                                    <div class="table-responsive">
                                        <table wire:loading.remove class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th rowspan="2" class="text-center">@lang('statistics.day')</th>
                                                    <th rowspan="2" class="text-center">@lang('statistics.day_stats.ready')</th>
                                                    <th rowspan="2" class="text-center">@lang('statistics.day_stats.empty')</th>
                                                    <th rowspan="2" class="text-center">@lang('statistics.day_stats.not_enough')</th>
                                                    <th rowspan="2" class="text-center">@lang('statistics.day_stats.max')</th>                                
                                                    <th rowspan="2" class="text-center">@lang('statistics.day_stats.service_hours')</th>
                                                    <th colspan="2" class="text-center">@lang('statistics.day_stats.available_time')</th>
                                                </tr>
                                                <tr>
                                                    <th class="text-center">@lang('statistics.day_stats.min_available_time')</th>
                                                    <th class="text-center">@lang('statistics.day_stats.max_available_time')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @php 
                                                $weekNum = 0;
                                                $summary = [
                                                    'ready' => 0,
                                                    'empty' => 0,
                                                    'not_enough' => 0,
                                                    'max' => 0,
                                                    'service_hour' => 0,
                                                    'min_available_time' => 0,
                                                    'max_available_time' => 0,
                                                ];
                                            @endphp
                                            @foreach ($day_stats as $day => $stat)
                                                <tr @if ( $weekNum != date("W", $day) && $weekNum !== 0)
                                                    style="border-top: 2px solid #000;"                               
                                                @endif>
                                                    <td class="text-center text-bold">
                                                        @if(isset($date_info[0][$day])) 
                                                            {{ date(__('app.format.date'), $day) }}, {{ __('event.weekdays_short.'.date('w', $day)) }}<br/>
                                                            <span class="badge badge-danger">{{ $date_info[0][$day]['note'] }}</span>
                                                        @else
                                                            <a href="javascript:void(0);" onclick="modal('{{ date("Y-m-d", $day) }}', {{ $groupId }})">
                                                                {{ date(__('app.format.date'), $day) }}, {{ __('event.weekdays_short.'.date('w', $day)) }}
                                                            </a>
                                                            @if(isset($date_info[2][$day])) 
                                                                <br/><span class="badge badge-success">{{ $date_info[2][$day]['note'] }}</span>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td class="text-right">{{$stat['ready']}}</td>
                                                    <td class="text-right">{{$stat['empty']}}</td>
                                                    <td class="text-right">{{$stat['not_enough']}}</td>
                                                    <td class="text-right">{{$stat['max']}}</td>                                
                                                    <td class="text-right">{{$stat['service_hour']}}</td>
                                                    <td class="text-right">{{$stat['min_available_time']}}</td>
                                                    <td class="text-right">{{$stat['max_available_time']}}</td>
                                                </tr>
                                                @php 
                                                    $weekNum = date("W", $day);
                                                    $summary['ready'] += $stat['ready'];
                                                    $summary['empty'] += $stat['empty'];
                                                    $summary['not_enough'] += $stat['not_enough'];
                                                    $summary['max'] += $stat['max'];
                                                    $summary['service_hour'] += $stat['service_hour'];
                                                    $summary['min_available_time'] += $stat['min_available_time'];
                                                    $summary['max_available_time'] += $stat['max_available_time'];
                                                @endphp
                                            @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th class="text-center"></th>
                                                    <th class="text-center">@lang('statistics.day_stats.ready')</th>
                                                    <th class="text-center">@lang('statistics.day_stats.empty')</th>
                                                    <th class="text-center">@lang('statistics.day_stats.not_enough')</th>
                                                    <th class="text-center">@lang('statistics.day_stats.max')</th>                                
                                                    <th class="text-center">@lang('statistics.day_stats.service_hours')</th>
                                                    <th class="text-center">@lang('statistics.day_stats.min_available_time')</th>
                                                    <th class="text-center">@lang('statistics.day_stats.max_available_time')</th>
                                                </tr>
                                                <tr>
                                                    <th>@lang('statistics.summary')</th> 
                                                    @foreach ($summary as $value)
                                                        <th class="text-right text-bold">
                                                            {{ $value }}
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="callout callout-info mt-4">
                                        <strong>@lang('statistics.help')</strong>:
                                        <ul class="mt-2">
                                            @foreach(__('statistics.day_stats_helper') as $key => $translate)
                                                <li>
                                                    <strong>{{ __('statistics.day_stats.'.$key) }}:</strong> 
                                                    {{ $translate }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div> <!-- end of first tab -->
                                <div wire:ignore.self class="tab-pane fade" id="tabs-publishers_data" role="tabpanel" aria-labelledby="tabs-publishers_data-tab">
                                    <table wire:loading.remove class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>@lang('group.users')</th>
                                                <th class="text-right">@lang('statistics.publisher.events')</th>
                                                <th class="text-right">@lang('statistics.publisher.days')</th>
                                                <th class="text-right">@lang('statistics.publisher.hours')</th>
                                                <th class="text-right">@lang('event.service.placements')</th>
                                                <th class="text-right">@lang('event.service.videos')</th>
                                                <th class="text-right">@lang('event.service.return_visits')</th>
                                                <th class="text-right">@lang('event.service.bible_studies')</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($users_stats as $stat)
                                                <tr>
                                                    <td class="text-bold">{{ $stat['name'] }}</td>
                                                    <td class="text-right">{{ $stat['events'] }}</td>
                                                    <td class="text-right">{{ count($stat['days']) }}</td>
                                                    <td class="text-right">{{ $stat['hours'] }}</td>
                                                    <td class="text-right">{{ $stat['placements'] }}</td>
                                                    <td class="text-right">{{ $stat['videos'] }}</td>
                                                    <td class="text-right">{{ $stat['return_visits'] }}</td>
                                                    <td class="text-right">{{ $stat['bible_studies'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <div class="callout callout-info mt-4">
                                        <strong>@lang('statistics.help')</strong>:
                                        <ul class="mt-2">
                                           <li><strong>@lang('statistics.publisher.hours')</strong>: @lang('statistics.publisher.hours_info')</li>
                                        </ul>
                                    </div>
                                </div> <!-- end of publishers tab -->
                                <div wire:ignore.self class="tab-pane fade" id="tabs-placements_data" role="tabpanel" aria-labelledby="tabs-placements_data-tab">
                                    <div class="table-responsive">
                                        <table wire:loading.remove class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>@lang('statistics.day')</th>
                                                    <th>@lang('event.service.language')</th>
                                                    <th class="text-right">@lang('event.service.placements')</th>
                                                    <th class="text-right">@lang('event.service.videos')</th>
                                                    <th class="text-right">@lang('event.service.return_visits')</th>
                                                    <th class="text-right">@lang('event.service.bible_studies')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($placements_stats as $day => $languages)
                                                    @foreach ($languages as $lang => $report)
                                                    @php
                                                        
                                                        $date = \Carbon\Carbon::parse($day);

                                                    @endphp
                                                    <tr>
                                                        <td>{{ $date->format(__('app.format.date')) }}</td>
                                                        <td>{{ $lang }}</td>
                                                        <td class="text-right">{{ $report['placements'] }}</td>
                                                        <td class="text-right">{{ $report['videos'] }}</td>
                                                        <td class="text-right">{{ $report['return_visits'] }}</td>
                                                        <td class="text-right">{{ $report['bible_studies'] }}</td>
                                                    </tr>    
                                                    @endforeach
                                                    
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="10" class="text-bold">@lang('statistics.summary')</th>
                                                </tr>
                                                @foreach ($placements_total as $lang => $total)
                                                    <tr>
                                                        <td></td>
                                                        <td>{{ $lang }}</td>
                                                        <td class="text-right">{{ $total['placements'] }}</td>
                                                        <td class="text-right">{{ $total['videos'] }}</td>
                                                        <td class="text-right">{{ $total['return_visits'] }}</td>
                                                        <td class="text-right">{{ $total['bible_studies'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tfoot>
                                        </table>
                                    </div>
                                </div> <!-- end of placements tab -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function modal(date, groupId) {
            livewire.emitTo('events.modal', 'openModal', date, groupId);
        }
    </script>
</div>
