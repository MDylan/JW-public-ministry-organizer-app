<div wire:poll.30000ms>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{__('app.menu-home')}}</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="#">{{__('app.menu-home')}}</a></li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
        <div class="card-columns">
            @forelse ($groups as $group)
            {{-- <div class="col-md-6 float-left"> --}}
                <div class="card card-primary card-outline" wire:ignore.self>
                    <div class="card-header">
                        <div class="card-title">
                            {{ $group['name'] }}                            
                        </div>
                        <div class="card-tools" wire:ignore>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                            <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-1">
                    <table class="table table-sm m-0">
                        <thead>
                            <tr>
                                <th class="text-center">@lang('event.date')</th>
                                <th class="text-center">@lang('event.status')</th>
                                <th class="text-center">@lang('event.eventsBar.title')</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- <tr><td colspan="3">@if(isset($dates[$group['id']])) {{ var_dump($dates[$group['id']]) }} @endif</td></tr> --}}
                                @foreach ($days as $day)
                                <tr @if (date("w", $day) == 0) style="border-bottom:2px solid #000;" @endif>
                                    <td class="text-center">
                                        
                                        @if ( ( ($available_days[$group['id']][$day]) 
                                                // && !isset($dates[$group['id']][0][date("Y-m-d", $day)]) 
                                              ) 
                                                // || isset($dates[$group['id']][2][date("Y-m-d", $day)])
                                            )    
                                        <a href="javascript:void(0);" onclick="modal({{$group['id']}}, '{{ date("Y-m-d", $day) }}')">
                                            {{ date("m.d", $day) }}, {{ __('event.weekdays_short.'.date("w", $day)) }}
                                        </a>
                                        @else 
                                            {{ date("m.d", $day) }}, {{ __('event.weekdays_short.'.date("w", $day)) }}
                                        @endif                                    
                                    </td>
                                    <td class="p-0"> 
                                        @if ( (isset($day_stat[$group['id']][$day])/* && !isset($dates[$group['id']][0][date("Y-m-d", $day)])*/ ))
                                            <div class="dayStat @if (isset($day_stat[$group['id']][$day]['event'])) userEvent @endif"
                                                style="height:35px;background: {{$day_stat[$group['id']][$day]['style'] }}"
                                            ></div>
                                        @endif
                                    </td>
                                    <td class="p-0">                                        
                                        @if ( isset($events[$group['id']][$day]) )
                                            <table class="table table-striped table-hover m-0">
                                                @foreach ($events[$group['id']][$day] as $event)
                                                    <tr>
                                                        <td class="text-center">
                                                            {{ $event['full_time'] }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>  
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                        <div class="row m-2">
                            <div class="col-12">
                                <a wire:click="changeGroup({{$group['id']}})" href="javascript:void(0);" class="btn btn-primary mr-1 mb-2 mb-md-0">
                                    <i class="fa fa-calendar mr-1"></i>
                                    @lang('app.jump_to_calendar')</a>
                                
                                    <a href="{{ route('groups.users', ['group' => $group['id']]) }}" class="btn btn-outline-secondary mr-1 mb-2 mb-md-0">
                                        <i class="fa fa-user-friends mr-1"></i>
                                        @lang('group.users')
                                    </a>

                                    <a href="{{ route('groups.news', ['group' => $group['id']]) }}" class="btn btn-info mb-2 mb-md-0">
                                        <i class="fa fa-file mr-1"></i>
                                        @lang('group.news')</a>
                            </div>
                        </div>
                    </div>
                </div>
            {{-- </div> --}}
            @empty
                {{-- <div class="col-md-12"> --}}
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                        <h5 class="m-0">@lang('app.information')</h5>
                        </div>
                        <div class="card-body">
                            <p>@lang('app.no_any_groups')</p>
                        </div>
                    </div>
                {{-- </div> --}}
            @endforelse
            <!-- /.col-md-6 -->
        </div>
        @if(count($groups) > 0)
            {{-- <div style="clear:both;"></div> --}}
            <!-- /.row -->
            <script>
                function modal(groupId, date) {
                    livewire.emitTo('events.modal', 'openModal', date, groupId);
                }
            </script>
        @endif
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
    @if(count($groups) > 0)
        @livewire('events.modal', ['groupId' => 0], key('eventsmodal'))
    @endif
</div>