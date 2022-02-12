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
                                <th class="text-center" style="width: 130px;">@lang('event.date')</th>
                                <th class="text-center" style="width: 130px;">@lang('event.status')</th>
                                <th class="text-center">@lang('event.eventsBar.title')</th>
                            </tr>
                        </thead>
                        <tbody>
                                @foreach ($days as $day)
                                <tr @if (date("w", $day) == 0) style="border-bottom:2px solid #000;" @endif>
                                    <td class="text-center position-relative">
                                        @if($available_days[$group['id']][$day]) 
                                            <a href="javascript:void(0);" onclick="modal({{$group['id']}}, '{{ date("Y-m-d", $day) }}')">
                                                @if(in_array($group_roles[$group['id']], ['admin', 'roler'])) 
                                                    @if(isset($notAccepts[$group['id']][date("Y-m-d", $day)]))
                                                        <i class="fas fa-balance-scale-right mr-1 mt-1 position-absolute" style="margin-left: -26px"></i>
                                                    @endif
                                                @endif
                                                {{ date("m.d", $day) }}, {{ __('event.weekdays_short.'.date("w", $day)) }}
                                            </a>
                                        @else 
                                            {{ date("m.d", $day) }}, {{ __('event.weekdays_short.'.date("w", $day)) }}
                                        @endif                                    
                                    </td>
                                    <td class="p-0"> 
                                        @if (isset($day_stat[$group['id']][$day]))
                                            <div class="dayStat @if (isset($day_stat[$group['id']][$day]['event'])) userEvent @endif "
                                                style="height:35px;background: {{$day_stat[$group['id']][$day]['style'] }}"></div>
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
                        <div class="row ml-2 mt-2 mr-2 mb-0">
                            <div class="col-12">
                                <a wire:click="changeGroup({{$group['id']}})" href="javascript:void(0);" class="btn btn-primary mr-1 mb-2">
                                    <i class="fa fa-calendar mr-1"></i>
                                    @lang('app.jump_to_calendar')</a>
                                
                                    <a href="{{ route('groups.users', ['group' => $group['id']]) }}" class="btn btn-outline-secondary mr-1 mb-2">
                                        <i class="fa fa-user-friends mr-1"></i>
                                        @lang('group.users')
                                    </a>

                                    <a href="{{ route('groups.news', ['group' => $group['id']]) }}" class="btn btn-info mb-2">
                                        <i class="fa fa-file mr-1"></i>
                                        @lang('group.news')</a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                        <h5 class="m-0">@lang('app.information')</h5>
                        </div>
                        <div class="card-body">
                            <p>@lang('app.no_any_groups')</p>
                        </div>
                    </div>
            @endforelse
            <!-- /.col-md-6 -->
        </div>
        @if(count($groups) > 0)
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